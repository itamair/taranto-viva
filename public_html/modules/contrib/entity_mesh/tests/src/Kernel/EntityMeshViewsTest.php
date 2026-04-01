<?php

namespace Drupal\Tests\entity_mesh\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\entity_mesh\Kernel\Traits\EntityMeshTestTrait;
use Drupal\user\Entity\Role;

/**
 * Tests the Entity Mesh link auditing for taxonomy terms and views.
 *
 * @group entity_mesh
 */
class EntityMeshViewsTest extends KernelTestBase {

  use ContentTypeCreationTrait;
  use UserCreationTrait;
  use EntityMeshTestTrait;

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
    'taxonomy',
    'views',
    'path_alias',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install the necessary schemas.
    $this->installEntitySchema('configurable_language');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('taxonomy_term');

    // Install schemas.
    $this->installSchema('entity_mesh', ['entity_mesh']);
    $this->installSchema('node', ['node_access']);

    // Install configs but skip entity_mesh to avoid schema validation errors.
    $this->installConfig(['filter', 'node', 'system', 'language', 'taxonomy', 'entity_mesh']);
    $entity_mesh = $this->config('entity_mesh.settings')->getRawData();
    $entity_mesh['target_types']['internal']['taxonomy_term']['enabled'] = TRUE;

    $this->config('entity_mesh.settings')->setData($entity_mesh)->save();
    // Create content type.
    $this->createContentType(['type' => 'page', 'name' => 'Page']);

    // Set up language configuration.
    $config = $this->config('language.negotiation');
    $config->set('url.prefixes', ['en' => 'en'])
      ->save();

    // Enable the body field in the default view mode.
    $this->container->get('entity_display.repository')
      ->getViewDisplay('node', 'page', 'full')
      ->setComponent('body', [
        // Show label above the body content.
        'label' => 'above',
        // Render as basic text.
        'type' => 'text_default',
      ])
      ->save();

    // Create filter format.
    $filter_format = FilterFormat::load('basic_html');
    if (!$filter_format) {
      $filter_format = FilterFormat::create([
        'format' => 'basic_html',
        'name' => 'Basic HTML',
        'filters' => [],
      ]);
      $filter_format->save();
    }

    // Create anonymous role if it doesn't exist.
    if (!Role::load(AccountInterface::ANONYMOUS_ROLE)) {
      Role::create(['id' => AccountInterface::ANONYMOUS_ROLE, 'label' => 'Anonymous user'])->save();
    }

    // Grant permissions to anonymous users.
    $this->grantPermissions(Role::load(AccountInterface::ANONYMOUS_ROLE), [
      'access content',
    ]);

    // Set entity_mesh to synchronous mode for tests.
    $config = $this->config('entity_mesh.settings');
    $config->set('processing_mode', 'synchronous');
    $config->set('synchronous_limit', 9999);
    $config->save();

    // Create vocabulary and taxonomy terms.
    $this->createTaxonomyTerms();

    // Set up path aliases for taxonomy terms.
    $this->createPathAliases();

    // Rebuild router.
    $this->container->get('router.builder')->rebuild();

    // Create example nodes with links to taxonomy terms and views.
    $this->createExampleNodes();
  }

  /**
   * Creates taxonomy terms for testing.
   */
  protected function createTaxonomyTerms() {
    // Create vocabulary.
    Vocabulary::create([
      'vid' => 'tags',
      'name' => 'Tags',
    ])->save();

    // Create term without alias.
    Term::create([
      'tid' => 1,
      'vid' => 'tags',
      'name' => 'Term without alias',
    ])->save();

    // Create term with alias.
    Term::create([
      'tid' => 2,
      'vid' => 'tags',
      'name' => 'Term with alias',
    ])->save();

    // Create unpublished term without alias.
    Term::create([
      'tid' => 3,
      'vid' => 'tags',
      'name' => 'Unpublished term without alias',
    // Set as unpublished.
      'status' => 0,
    ])->save();
  }

  /**
   * Creates path aliases for taxonomy terms.
   */
  protected function createPathAliases() {
    // Create alias for the second taxonomy term.
    $path_alias = \Drupal::entityTypeManager()->getStorage('path_alias')->create([
      'path' => '/taxonomy/term/2',
      'alias' => '/example-category',
      'langcode' => 'en',
    ]);
    $path_alias->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function createExampleNodes() {
    // Create a node with links to taxonomy terms and views.
    $html_content = '
      <p>Taxonomy term without alias: <a href="/taxonomy/term/1">Term without alias</a></p>
      <p>Taxonomy term with alias: <a href="/example-category">Term with alias</a></p>
      <p>View with access denied: <a href="/admin/content">Admin content</a></p>
      <p>Unpublished taxonomy term without alias: <a href="/taxonomy/term/3">Unpublished term without alias</a></p>
    ';

    $node = Node::create([
      'type' => 'page',
      'title' => 'Test Node with Links',
      'nid' => 1,
      'body' => [
        'value' => $html_content,
        'format' => 'basic_html',
      ],
    ]);
    $node->save();
  }

  /**
   * Tests different types of links.
   *
   * @dataProvider linkCasesProvider
   */
  public function testLinks(
    $source_entity_id,
    $target_href,
    $expected_target_link_type,
    $expected_target_subcategory = NULL,
    $expected_target_title = NULL,
    $expected_target_scheme = NULL,
    $expected_target_entity_type = NULL,
    $expected_target_entity_bundle = NULL,
    $expected_target_entity_id = NULL,
    $expected_target_entity_langcode = NULL,
    $expected_source_entity_type = NULL,
    $expected_source_entity_bundle = NULL,
    $expected_source_entity_langcode = NULL,
    $expected_source_title = NULL,
  ) {
    // Fetch records from entity_mesh table for assertions.
    $records = $this->fetchEntityMeshRecords();

    // Filter records based on node ID and link type.
    $filtered = array_filter($records, function ($record) use ($source_entity_id, $target_href) {
      return $record['source_entity_id'] == $source_entity_id &&
        $record['target_href'] === $target_href;
    });

    // Extract the record by matching 'target_href' with $expected_href.
    $record = reset($filtered);

    $this->assertNotEmpty($record, "No record found with target_href: $target_href");

    $checks = [
      'target_link_type'       => $expected_target_link_type,
      'subcategory'            => $expected_target_subcategory,
      'target_title'           => $expected_target_title,
      'target_scheme'          => $expected_target_scheme,
      'target_entity_type'     => $expected_target_entity_type,
      'target_entity_bundle'   => $expected_target_entity_bundle,
      'target_entity_id'       => $expected_target_entity_id,
      'target_entity_langcode' => $expected_target_entity_langcode,
      'source_entity_type'     => $expected_source_entity_type,
      'source_entity_bundle'   => $expected_source_entity_bundle,
      'source_entity_langcode' => $expected_source_entity_langcode,
      'source_title'           => $expected_source_title,
    ];

    foreach ($checks as $key => $expectedValue) {
      if ($expectedValue !== NULL) {
        $this->assertEquals($expectedValue, $record[$key]);
      }
    }
  }

  /**
   * Provides test cases for different types of links.
   */
  public static function linkCasesProvider() {
    $defaults = [
      'source_entity_id'                 => NULL,
      'target_href'                      => NULL,
      'expected_target_link_type'        => NULL,
      'expected_target_subcategory'      => NULL,
      'expected_target_title'            => NULL,
      'expected_target_scheme'           => NULL,
      'expected_target_entity_type'      => NULL,
      'expected_target_entity_bundle'    => NULL,
      'expected_target_entity_id'        => NULL,
      'expected_target_entity_langcode'  => NULL,
      'expected_source_entity_type'      => NULL,
      'expected_source_entity_bundle'    => NULL,
      'expected_source_entity_langcode'  => NULL,
      'expected_source_title'            => NULL,
    ];

    return [
      'Taxonomy term without alias' => array_merge($defaults, [
        'source_entity_id'                => 1,
        'target_href'                     => '/taxonomy/term/1',
        'expected_target_link_type'       => 'internal',
        'expected_target_subcategory'     => 'link',
        'expected_target_entity_type'     => 'taxonomy_term',
        'expected_target_entity_id'       => '1',
      ]),

      'Taxonomy term with alias' => array_merge($defaults, [
        'source_entity_id'                => 1,
        'target_href'                     => '/example-category',
        'expected_target_link_type'       => 'internal',
        'expected_target_subcategory'     => 'link',
        'expected_target_entity_type'     => 'taxonomy_term',
        'expected_target_entity_id'       => '2',
      ]),

      'View with access denied' => array_merge($defaults, [
        'source_entity_id'                => 1,
        'target_href'                     => '/admin/content',
        'expected_target_link_type'       => 'internal',
        'expected_target_subcategory'     => 'access-denied-link',
      ]),

      'Unpublished taxonomy term without alias' => array_merge($defaults, [
        'source_entity_id'                => 1,
        'target_href'                     => '/taxonomy/term/3',
        'expected_target_link_type'       => 'internal',
        'expected_target_subcategory'     => 'access-denied-link',
        'expected_target_entity_type'     => 'taxonomy_term',
        'expected_target_entity_id'       => '3',
      ]),
    ];
  }

}
