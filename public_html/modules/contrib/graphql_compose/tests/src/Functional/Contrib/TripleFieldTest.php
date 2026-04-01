<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose\Functional\Contrib;

use Drupal\Tests\graphql_compose\Functional\GraphQLComposeBrowserTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\NodeInterface;

/**
 * Test the Triple Field integration.
 *
 * @group graphql_compose
 */
class TripleFieldTest extends GraphQLComposeBrowserTestBase {

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
    'triples_field',
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

    // Create the triple field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_str_num_str',
      'entity_type' => 'node',
      'type' => 'triples_field',
      'settings' => [
        'storage' => [
          'first' => [
            'type' => 'string',
            'maxlength' => 50,
          ],
          'second' => [
            'type' => 'integer',
            'maxlength' => 50,
            'precision' => 10,
            'scale' => 2,
            'datetime_type' => 'datetime',
          ],
          'third' => [
            'type' => 'string',
            'maxlength' => 50,
          ],
        ],
      ],
    ]);

    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'test',
      'label' => 'Triple field test',
    ]);

    $field->save();

    // Create a test node.
    $this->node = $this->createNode([
      'type' => 'test',
      'title' => 'Test',
      'body' => [
        'value' => 'Test content',
        'format' => 'plain_text',
      ],
      'status' => 1,
      'field_str_num_str' => [
        'first' => 'AAAAAAA',
        'second' => 123,
        'third' => 'BBBBBBB',
      ],
    ]);

    $this->setEntityConfig('node', 'test', [
      'enabled' => TRUE,
      'query_load_enabled' => TRUE,
    ]);

    $this->setFieldConfig('node', 'test', 'field_str_num_str', [
      'enabled' => TRUE,
    ]);
  }

  /**
   * Test triple field has content.
   */
  public function testTripleField(): void {
    $query = <<<GQL
      query {
        node(id: "{$this->node->uuid()}") {
          ... on NodeTest {
            strNumStr {
              __typename
              first
              second
              third
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertNotNull($content['data']['node']['strNumStr'] ?? NULL);

    $strNumStr = $content['data']['node']['strNumStr'];

    $this->assertEquals('TripleStringIntString', $strNumStr['__typename']);
    $this->assertEquals('AAAAAAA', $strNumStr['first']);
    $this->assertEquals(123, $strNumStr['second']);
    $this->assertEquals('BBBBBBB', $strNumStr['third']);
  }

}
