<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose\Functional\Core;

use Drupal\Tests\graphql_compose\Functional\GraphQLComposeBrowserTestBase;
use Drupal\block_content\BlockContentInterface;
use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Test block plugin loading.
 *
 * @group graphql_compose
 */
class BlockTest extends GraphQLComposeBrowserTestBase {

  /**
   * Block content to place.
   *
   * @var \Drupal\block_content\BlockContentInterface
   */
  protected BlockContentInterface $blockContent;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'graphql_compose_blocks',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a block type to place.
    $block_type = BlockContentType::create([
      'id' => 'basic_block',
      'label' => 'Basic block',
    ]);
    $block_type->save();

    $field = FieldConfig::loadByName('block_content', $block_type->id(), 'body');
    $field_storage = FieldStorageConfig::loadByName('block_content', 'body');

    if (!$field_storage) {
      $field_storage = FieldStorageConfig::create([
        'field_name' => 'body',
        'entity_type' => 'block_content',
        'type' => 'text_with_summary',
      ]);
      $field_storage->save();
    }

    if (!$field) {
      $field = FieldConfig::create([
        'field_storage' => $field_storage,
        'bundle' => $block_type->id(),
        'label' => 'Body',
        'settings' => [
          'display_summary' => FALSE,
          'allowed_formats' => [],
        ],
      ]);
      $field->save();
    }

    \Drupal::service('entity_display.repository')
      ->getViewDisplay('block_content', $block_type->id())
      ->setComponent('body', [
        'type' => 'text_default',
      ])
      ->save();

    $this->blockContent = BlockContent::create([
      'info' => 'My content block',
      'type' => $block_type->id(),
      'body' => [
        [
          'value' => 'This is the block content',
          'format' => filter_default_format(),
        ],
      ],
    ]);

    $this->blockContent->save();

    // Enable block type.
    $this->setEntityConfig('block_content', 'basic_block', [
      'enabled' => TRUE,
    ]);

    $this->setFieldConfig('block_content', 'basic_block', 'body', [
      'enabled' => TRUE,
    ]);
  }

  /**
   * Test load entity by id.
   */
  public function testPluginBlock(): void {
    $query = <<<GQL
      query {
        block(id: "system_powered_by_block") {
          ... on BlockPlugin {
            id
            title
            render
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertEquals(
      'system_powered_by_block',
      $content['data']['block']['id']
    );

    $this->assertNull($content['data']['block']['title']);

    $this->assertStringContainsStringIgnoringCase(
      'Powered by',
      $content['data']['block']['render']
    );
  }

  /**
   * Test load entity by id.
   */
  public function testBlockContent(): void {
    $query = <<<GQL
      query {
        block(id: "block_content:{$this->blockContent->uuid()}") {
          ... on BlockContent {
            id
            title
            render
            entity {
              ... on BlockContentBasicBlock {
                id
                title
                reusable
                changed {
                  timestamp
                }
                body {
                  processed
                }
              }
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertStringContainsStringIgnoringCase(
      'This is the block content',
      $content['data']['block']['entity']['body']['processed']
    );

    $this->assertStringContainsStringIgnoringCase(
      'This is the block content',
      $content['data']['block']['render']
    );
  }

}
