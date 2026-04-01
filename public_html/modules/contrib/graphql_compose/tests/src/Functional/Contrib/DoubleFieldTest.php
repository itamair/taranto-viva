<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose\Functional\Contrib;

use Drupal\Tests\graphql_compose\Functional\GraphQLComposeBrowserTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\NodeInterface;

/**
 * Test the Double/Triple Field integration.
 *
 * @group graphql_compose
 */
class DoubleFieldTest extends GraphQLComposeBrowserTestBase {

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
    'double_field',
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

    // Create the double field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_str_num',
      'entity_type' => 'node',
      'type' => 'double_field',
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
        ],
      ],
    ]);

    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'test',
      'label' => 'Double field test',
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
      'field_str_num' => [
        'first' => 'AAAAAAA',
        'second' => 123,
      ],
    ]);

    $this->setEntityConfig('node', 'test', [
      'enabled' => TRUE,
      'query_load_enabled' => TRUE,
    ]);

    $this->setFieldConfig('node', 'test', 'field_str_num', [
      'enabled' => TRUE,
    ]);
  }

  /**
   * Test double field has content.
   */
  public function testDoubleField(): void {
    $query = <<<GQL
      query {
        node(id: "{$this->node->uuid()}") {
          ... on NodeTest {
            strNum {
              __typename
              first
              second
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertNotNull($content['data']['node']['strNum'] ?? NULL);

    $strNum = $content['data']['node']['strNum'];

    $this->assertEquals('DoubleStringInt', $strNum['__typename']);
    $this->assertEquals('AAAAAAA', $strNum['first']);
    $this->assertEquals(123, $strNum['second']);
  }

}
