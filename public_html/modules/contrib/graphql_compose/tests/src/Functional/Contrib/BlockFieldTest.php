<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose\Functional\Contrib;

use Drupal\Tests\block_field\Traits\BlockFieldTestTrait;
use Drupal\Tests\graphql_compose\Functional\GraphQLComposeBrowserTestBase;
use Drupal\node\NodeInterface;

/**
 * Test the BlockField Field integration.
 *
 * @group legacy
 */
class BlockFieldTest extends GraphQLComposeBrowserTestBase {

  use BlockFieldTestTrait;

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
    'block_field',
    'graphql_compose_blocks',
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

    $blocks = [
      'plugin_ids' => [
        'system_branding_block',
        'system_powered_by_block',
      ],
    ];

    $this->createBlockField('node', 'test', 'field_block', 'Block', 'blocks', $blocks);

    $this->node = $this->createNode([
      'type' => 'test',
      'title' => 'Test',
      'body' => [
        'value' => 'Test content',
        'format' => 'plain_text',
      ],
      'status' => 1,
      'field_block' => [
        'plugin_id' => 'system_powered_by_block',
        'settings' => [
          'label' => 'Custom label',
          'label_display' => TRUE,
          'access' => TRUE,
        ],
      ],
    ]);

    $this->setEntityConfig('node', 'test', [
      'enabled' => TRUE,
      'query_load_enabled' => TRUE,
    ]);

    $this->setFieldConfig('node', 'test', 'field_block', [
      'enabled' => TRUE,
    ]);
  }

  /**
   * Test load entity by id.
   */
  public function testBlockField(): void {
    $query = <<<GQL
      query {
        node(id: "{$this->node->uuid()}") {
          ... on NodeTest {
            block {
              __typename
              ... on BlockPlugin {
                id
                render
                title
              }
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertNotNull($content['data']['node']['block'] ?? NULL);

    $block = $content['data']['node']['block'];

    $this->assertEquals('system_powered_by_block', $block['id']);
    $this->assertStringContainsStringIgnoringCase('Powered by', $block['render']);
    $this->assertEquals('Custom label', $block['title']);
  }

}
