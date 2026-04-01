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
 * Test how unions interact with entity reference fields.
 *
 * @group graphql_compose
 */
class EntityUnionTest extends GraphQLComposeBrowserTestBase {

  use MediaTypeCreationTrait;

  /**
   * The test node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $node;

  /**
   * The test document.
   *
   * @var \Drupal\media\MediaInterface
   */
  protected MediaInterface $document;

  /**
   * The test image.
   *
   * @var \Drupal\media\MediaInterface
   */
  protected MediaInterface $image;

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
    $this->createMediaType('file', ['id' => 'document']);
    $this->createMediaType('file', ['id' => 'not_enabled']);
    $this->createMediaType('file', ['id' => 'not_targeted']);

    $this->createContentType([
      'type' => 'test',
      'name' => 'Test node type',
    ]);

    FieldStorageConfig::create([
      'field_name' => 'field_single',
      'type' => 'entity_reference',
      'entity_type' => 'node',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'media',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_single',
      'entity_type' => 'node',
      'bundle' => 'test',
      'label' => 'Single',
      'settings' => [
        'handler' => 'default',
        'handler_settings' => [
          'target_bundles' => [
            'image' => 'image',
          ],
        ],
      ],
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_multiple',
      'type' => 'entity_reference',
      'entity_type' => 'node',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'media',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_multiple',
      'entity_type' => 'node',
      'bundle' => 'test',
      'label' => 'Multi',
      'settings' => [
        'handler' => 'default',
        'handler_settings' => [
          'target_bundles' => [
            'image' => 'image',
            'document' => 'document',
          ],
        ],
      ],
    ])->save();

    $this->image = Media::create([
      'bundle' => 'image',
      'name' => 'Cat bread',
      'status' => TRUE,
    ]);
    $this->image->save();

    $this->document = Media::create([
      'bundle' => 'document',
      'name' => 'Dog bread',
      'status' => TRUE,
    ]);
    $this->document->save();

    $this->node = $this->createNode([
      'type' => 'test',
      'title' => 'Test',
      'field_single' => [
        ['target_id' => $this->image->id()],
      ],
      'field_multiple' => [
        ['target_id' => $this->image->id()],
        ['target_id' => $this->document->id()],
      ],
      'status' => 1,
    ]);

    $this->setEntityConfig('media', 'image', [
      'enabled' => TRUE,
    ]);

    $this->setEntityConfig('media', 'document', [
      'enabled' => TRUE,
    ]);

    $this->setEntityConfig('media', 'not_targeted', [
      'enabled' => TRUE,
    ]);

    $this->setEntityConfig('node', 'test', [
      'enabled' => TRUE,
      'query_load_enabled' => TRUE,
    ]);

    $this->setFieldConfig('node', 'test', 'field_single', [
      'enabled' => TRUE,
    ]);

    $this->setFieldConfig('node', 'test', 'field_multiple', [
      'enabled' => TRUE,
    ]);
  }

  /**
   * Test load entity with simple unions.
   */
  public function testUnionSimpleLoadUnions(): void {
    $query = <<<GQL
      query {
        node(id: "{$this->node->uuid()}") {
          ... on NodeTest {
            single {
              ... on MediaInterface {
                __typename
              }
            }
            multiple {
              ... on MediaInterface {
                __typename
              }
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertEquals(
      [
        'node' => [
          'single' => [
            ['__typename' => 'MediaImage'],
          ],
          'multiple' => [
            ['__typename' => 'MediaImage'],
            ['__typename' => 'MediaDocument'],
          ],
        ],
      ],
      $content['data']
    );
  }

  /**
   * Test load entity by id.
   */
  public function testUnionGenericUnionTypes(): void {
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
      'MediaUnion',
      $map['single']['type']['ofType']['ofType']['name']
    );

    $this->assertEquals(
      'MediaUnion',
      $map['multiple']['type']['ofType']['ofType']['name']
    );

    $names = array_map(
      fn($item) => $item['name'],
      $map['single']['type']['ofType']['ofType']['possibleTypes']
    );

    $this->assertContains('MediaDocument', $names);
    $this->assertContains('MediaImage', $names);
    $this->assertContains('MediaNotTargeted', $names);
    $this->assertCount(3, $names);
  }

  /**
   * Test load entity by id.
   */
  public function testUnionSpecificUnionTypes(): void {

    $this->setConfig('settings.simple_unions', FALSE);

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
      'MediaImage',
      $map['single']['type']['ofType']['ofType']['name']
    );

    $this->assertEquals(
      'NodeTestMultipleUnion',
      $map['multiple']['type']['ofType']['ofType']['name']
    );

    $names = array_map(
      fn($item) => $item['name'],
      $map['multiple']['type']['ofType']['ofType']['possibleTypes']
    );

    $this->assertContains('MediaImage', $names);
    $this->assertContains('MediaDocument', $names);
    $this->assertCount(2, $names);
  }

}
