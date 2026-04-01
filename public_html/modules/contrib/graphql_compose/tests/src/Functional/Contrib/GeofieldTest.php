<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose\Functional\Contrib;

use Drupal\Tests\graphql_compose\Functional\GraphQLComposeBrowserTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\NodeInterface;

/**
 * Test the Geofield Field integration.
 *
 * @group graphql_compose
 */
class GeofieldTest extends GraphQLComposeBrowserTestBase {

  /**
   * We aren't concerned with strict config schema for contrib.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE; // @phpcs:ignore

  /**
   * The test node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $node;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'geofield',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createContentType([
      'type' => 'test',
      'name' => 'Test node type',
    ]);

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_geo',
      'entity_type' => 'node',
      'type' => 'geofield',
    ]);

    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'test',
      'label' => 'Geo',
    ]);

    $field->save();

    $this->node = $this->createNode([
      'type' => 'test',
      'title' => 'Test',
      'body' => [
        'value' => 'Test content',
        'format' => 'plain_text',
      ],
      'field_geo' => \Drupal::service('geofield.wkt_generator')->WktGenerateGeometry(),
      'status' => 1,
    ]);

    $this->setEntityConfig('node', 'test', [
      'enabled' => TRUE,
      'query_load_enabled' => TRUE,
    ]);

    $this->setFieldConfig('node', 'test', 'field_geo', [
      'enabled' => TRUE,
    ]);
  }

  /**
   * Test load entity by id.
   */
  public function testGeofield(): void {
    $query = <<<GQL
      query {
        node(id: "{$this->node->uuid()}") {
          ... on NodeTest {
            geo {
              geoType
              lon
              lat
              left
              top
              right
              bottom
              geohash
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertNotNull($content['data']['node']['geo'] ?? NULL);

    $geo = $content['data']['node']['geo'];
    $geom = \Drupal::service('geofield.geophp')->load($this->node->field_geo->value);

    $this->assertEquals($geo['geoType'], $geom->geometryType());
    $this->assertEquals($geo['lon'], $geom->getCentroid()->getX());
    $this->assertEquals($geo['lat'], $geom->getCentroid()->getY());
    $this->assertEquals($geo['left'], $geom->getBBox()['minx']);
    $this->assertEquals($geo['top'], $geom->getBBox()['maxy']);
    $this->assertEquals($geo['right'], $geom->getBBox()['maxx']);
    $this->assertEquals($geo['bottom'], $geom->getBBox()['miny']);
    $this->assertEquals($geo['geohash'], $geom->out('geohash'));

  }

}
