<?php

namespace Drupal\Tests\entity_mesh\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\entity_mesh\Kernel\Traits\EntityMeshTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;

/**
 * Tests the Entity Mesh source types configuration.
 *
 * @group entity_mesh
 */
class EntityMeshSourceTypesTest extends KernelTestBase {
  use ContentTypeCreationTrait;
  use EntityMeshTestTrait;
  use UserCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array<string>
   */
  protected static $modules = [
    'system',
    'node',
    'user',
    'field',
    'filter',
    'text',
    'language',
    'entity_mesh',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installSchema('entity_mesh', ['entity_mesh']);
    $this->installConfig(['filter', 'node', 'system', 'language', 'entity_mesh']);
    $this->installSchema('node', ['node_access']);

    $filter_format = FilterFormat::load('basic_html');
    if (!$filter_format) {
      $filter_format = FilterFormat::create([
        'format' => 'basic_html',
        'name' => 'Basic HTML',
        'filters' => [],
      ]);
      $filter_format->save();
    }

    if (!Role::load(AccountInterface::ANONYMOUS_ROLE)) {
      Role::create(['id' => AccountInterface::ANONYMOUS_ROLE, 'label' => 'Anonymous user'])->save();
    }

    $this->grantPermissions(Role::load(AccountInterface::ANONYMOUS_ROLE), [
      'access content',
    ]);

    // Create content types.
    $this->createContentType(['type' => 'page', 'name' => 'Page']);
    $this->createContentType(['type' => 'article', 'name' => 'Article']);
    $this->createContentType(['type' => 'landing', 'name' => 'Landing']);
    $this->createContentType(['type' => 'review', 'name' => 'Review']);

  }

  /**
   * Creates a node with given content.
   */
  protected function createExampleNodes() {
    // Empty implementation as we'll create nodes in specific tests.
  }

  /**
   * Tests that only configured content types are processed.
   */
  public function testConfiguredContentTypes() {
    // Configure Entity Mesh to process only article and page content types.
    $config = $this->config('entity_mesh.settings');
    $config->set('source_types', [
      'node' => [
        'enabled' => TRUE,
        'bundles' => [
          'article' => TRUE,
          'page' => TRUE,
          'landing' => FALSE,
          'review' => FALSE,
        ],
      ],
    ])->save();

    // Create nodes of different content types with external links.
    $nodes = [];
    $content_types = ['article', 'page', 'landing', 'review'];
    foreach ($content_types as $type) {
      $nodes[$type] = Node::create([
        'type' => $type,
        'title' => "Test $type",
        'body' => [
          'value' => '<p><a href="https://example.com">External Link</a></p>',
          'format' => 'basic_html',
        ],
      ]);
      $nodes[$type]->save();
    }

    // Fetch records from entity_mesh table.
    $records = $this->fetchEntityMeshRecords();

    // Filter records by target href.
    $processed_nodes = [];
    foreach ($records as $record) {
      if ($record['target_href'] === 'https://example.com') {
        $processed_nodes[] = $record['source_entity_bundle'];
      }
    }

    // Assert only article and page nodes were processed.
    $this->assertContains('article', $processed_nodes, 'Article node was processed');
    $this->assertContains('page', $processed_nodes, 'Page node was processed');
    $this->assertNotContains('landing', $processed_nodes, 'Landing node was not processed');
    $this->assertNotContains('review', $processed_nodes, 'Review node was not processed');
  }

  /**
   * Tests that no nodes are processed when node entity type is disabled.
   */
  public function testDisabledNodeEntityType() {
    // Configure Entity Mesh to disable node entity type.
    $config = $this->config('entity_mesh.settings');
    $config->set('source_types', [
      'node' => [
        'enabled' => FALSE,
        'bundles' => [],
      ],
    ])->save();

    // Create a test node.
    $node = Node::create([
      'type' => 'page',
      'title' => 'Test Page',
      'body' => [
        'value' => '<p><a href="https://example.com">External Link</a></p>',
        'format' => 'basic_html',
      ],
    ]);
    $node->save();

    // Fetch records from entity_mesh table.
    $records = $this->fetchEntityMeshRecords();

    // Assert no records were created.
    $this->assertEmpty($records, 'No records were created when node entity type is disabled');
  }

}
