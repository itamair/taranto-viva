<?php

declare(strict_types=1);

namespace Drupal\Tests\search_api_typesense\Functional\Plugin\Block;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api_typesense\Entity\TypesenseSchema;
use Drupal\Tests\BrowserTestBase;

/**
 * @coversDefaultClass \Drupal\search_api_typesense\Plugin\Block\TypesenseSearchBlock
 *
 * @group search_api_typesense
 */
class TypesenseSearchBlockTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'node',
    'search_api',
    'search_api_typesense',
    'search_api_typesense_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createContentType(['type' => 'articles', 'name' => 'Article']);
    $this->createNode(['type' => 'articles', 'title' => 'Test node']);

    $server = Server::create([
      'id' => 'typesense',
      'name' => 'Typesense',
      'backend' => 'search_api_typesense',
      'backend_config' => [
        'nodes' => [
          [
            'host' => 'localhost',
            'port' => 8108,
            'protocol' => 'http',
          ],
        ],
        'admin_api_key' => 'key',
      ],
    ]);
    $server->save();

    TypesenseSchema::create([
      'id' => 'test_index_typesense',
      'fields' => [
        'title' => [
          'type' => 'string',
          'facet' => FALSE,
          'optional' => FALSE,
          'index' => TRUE,
          'store' => TRUE,
          'sort' => TRUE,
        ],
      ],
      'enable_embedding' => FALSE,
      'embedding_fields' => [],
      'embedding_model' => '',
      'chunk_size' => 1000,
      'chunk_overlap_size' => 200,
      'chunk_prepend_fields' => [],
      'default_sorting_field' => '-none-',
    ])->save();

    $index = Index::create([
      'id' => 'test_index',
      'name' => 'Test Index',
      'server' => 'typesense',
      'datasource_settings' => [
        'entity:node' => [
          'bundles' => [
            'default' => FALSE,
            'selected' => [
              'articles',
            ],
          ],
          'languages' => [
            'default' => TRUE,
            'selected' => [],
          ],
        ],
      ],
      'processor_settings' => [],
      'field_settings' => [
        'title' => [
          'label' => 'Title',
          'type' => 'typesense_string',
          'datasource_id' => 'entity:node',
          'property_path' => 'title',
          'dependencies' => [
            'config' => [
              'field.storage.node.title',
            ],
          ],
        ],
      ],
      'options' => [
        'index_directly' => TRUE,
        'cron_limit' => 50,
      ],
    ]);
    $index->save();

    $this->placeBlock('search_api_typesense_search_block', [
      'id' => 'search_api_typesense_search_block',
      'server' => 'typesense',
      'collections' => ['test_index'],
    ]);
  }

  /**
   * Tests the missing read-only API key error message.
   *
   * @covers ::build
   */
  public function testBlockWithoutValidReadonlyKey(): void {
    $this->drupalGet('<front>');
    $this->assertSession()->statusMessageExists(MessengerInterface::TYPE_ERROR);
    $this->assertSession()->statusMessageContains('The search-only API key is not configured.');
  }

}
