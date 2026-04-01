<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose\Functional\Core;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Tests\graphql_compose\Functional\GraphQLComposeBrowserTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;

/**
 * Test to ensure unsupported type is returned for entities not in the schema.
 *
 * @group graphql_compose
 */
class UnsupportedTest extends GraphQLComposeBrowserTestBase {

  use MediaTypeCreationTrait;

  /**
   * The test node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $node;

  /**
   * The test media.
   *
   * @var \Drupal\media\MediaInterface
   */
  protected MediaInterface $media;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'media',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createMediaType('image', ['id' => 'image']);

    $this->media = Media::create([
      'bundle' => 'image',
      'name' => 'Cat bread',
      'status' => TRUE,
    ]);
    $this->media->save();

    $this->createContentType([
      'type' => 'test',
      'name' => 'Test node type',
    ]);

    FieldStorageConfig::create([
      'field_name' => 'field_ref',
      'type' => 'entity_reference',
      'entity_type' => 'node',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'media',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_ref',
      'entity_type' => 'node',
      'bundle' => 'test',
      'label' => 'Reference',
      'settings' => [
        'handler' => 'default',
        'handler_settings' => [
          'target_bundles' => [
            'image' => 'image',
          ],
        ],
      ],
    ])->save();

    $this->node = $this->createNode([
      'type' => 'test',
      'title' => 'Test',
      'field_ref' => [
        ['target_id' => $this->media->id()],
      ],
    ]);

    $this->setEntityConfig('node', 'test', [
      'enabled' => TRUE,
      'query_load_enabled' => TRUE,
    ]);

    $this->setFieldConfig('node', 'test', 'field_ref', [
      'enabled' => TRUE,
    ]);
  }

  /**
   * Ensure the type is UnsupportedType.
   */
  public function testUnsupportedType(): void {
    $query = <<<GQL
      query {
        __type(name: "NodeTest") {
          name
          fields {
            name
            type {
              ofType {
                ofType {
                  name
                  possibleTypes {
                    name
                  }
                }
              }
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $map = [];
    foreach ($content['data']['__type']['fields'] as $field) {
      $map[$field['name']] = $field;
    }

    $this->assertEquals(
      'UnsupportedType',
      $map['ref']['type']['ofType']['ofType']['name']
    );
  }

  /**
   * Test unsupported field value is true.
   */
  public function testUnsupportedValue(): void {
    $query = <<<GQL
      query {
        node(id: "{$this->node->uuid()}") {
          ... on NodeTest {
            id
            title
            ref {
              unsupported
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertNotEmpty($content['data']['node']);
    $node = $content['data']['node'];

    $this->assertEquals($this->node->uuid(), $node['id']);
    $this->assertTrue($node['ref'][0]['unsupported']);
  }

  /**
   * Test unsupported field fails.
   */
  public function testUnsupportedFailure(): void {
    $query = <<<GQL
      query {
        node(id: "{$this->node->uuid()}") {
          ... on NodeTest {
            id
            title
            ref {
              id
            }
          }
        }
      }
    GQL;

    try {
      $content = $this->executeQuery($query);
    }
    catch (\Exception) {
      // Swallow errors.
    }

    $this->assertStringContainsStringIgnoringCase(
      'Cannot query field "id" on type "UnsupportedType".',
      $content['errors'][0]['message'] ?? NULL
    );
  }

}
