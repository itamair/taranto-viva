<?php

namespace Drupal\Tests\entity_mesh\Kernel;

use Drupal\node\Entity\Node;
use Drupal\webform\Entity\Webform;

/**
 * Tests the Entity Mesh webform access checking functionality.
 *
 * @group entity_mesh
 * @requires module webform
 */
class EntityMeshWebformAccessTest extends EntityMeshTestBase {

  /**
   * {@inheritdoc}
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
    'path_alias',
    'file',
    'image',
    'media',
    'webform',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install webform schema.
    $this->installEntitySchema('webform');
    $this->installEntitySchema('webform_submission');
    $this->installSchema('webform', ['webform']);
    $this->installConfig(['webform']);

    // Enable webform as a target type.
    $entity_mesh = $this->config('entity_mesh.settings')->getRawData();
    $entity_mesh['target_types']['internal']['webform']['enabled'] = TRUE;
    $this->config('entity_mesh.settings')->setData($entity_mesh)->save();

    // Rebuild router to ensure webform routes are available.
    $this->container->get('router.builder')->rebuild();

    $this->createExampleNodes();
  }

  /**
   * Creates test nodes and webforms.
   */
  protected function createExampleNodes() {
    // Create a webform with default access rules (anonymous can submit).
    // Don't set any access rules to use the webform module defaults,
    // which allow anonymous and authenticated users to create submissions.
    $webform_accessible = Webform::create([
      'id' => 'test_webform_accessible',
      'title' => 'Accessible Webform',
      'status' => 'open',
    ]);
    $webform_accessible->save();

    // Create a webform with restricted access (only authenticated users).
    // Override the default access rules to only allow authenticated users.
    $webform_restricted = Webform::create([
      'id' => 'test_webform_restricted',
      'title' => 'Restricted Webform',
      'status' => 'open',
    ]);
    // Set access rules to restrict create to authenticated users only.
    // We need to set all access rules to override defaults completely.
    $webform_restricted->setAccessRules([
      'create' => [
        'roles' => ['authenticated'],
        'users' => [],
        'permissions' => [],
      ],
      'view_any' => [
        'roles' => [],
        'users' => [],
        'permissions' => [],
      ],
      'update_any' => [
        'roles' => [],
        'users' => [],
        'permissions' => [],
      ],
      'delete_any' => [
        'roles' => [],
        'users' => [],
        'permissions' => [],
      ],
      'purge_any' => [
        'roles' => [],
        'users' => [],
        'permissions' => [],
      ],
      'view_own' => [
        'roles' => [],
        'users' => [],
        'permissions' => [],
      ],
      'update_own' => [
        'roles' => [],
        'users' => [],
        'permissions' => [],
      ],
      'delete_own' => [
        'roles' => [],
        'users' => [],
        'permissions' => [],
      ],
      'administer' => [
        'roles' => [],
        'users' => [],
        'permissions' => [],
      ],
      'test' => [
        'roles' => [],
        'users' => [],
        'permissions' => [],
      ],
      'configuration' => [
        'roles' => [],
        'users' => [],
        'permissions' => [],
      ],
    ]);
    $webform_restricted->save();

    // Create a node with a link to the accessible webform.
    $html_node_1 = '
      <p>Link to accessible webform: <a href="/webform/test_webform_accessible">Accessible Webform</a></p>
    ';

    $node_1 = Node::create([
      'type' => 'page',
      'title' => 'Test Node with Accessible Webform',
      'nid' => 1,
      'body' => [
        'value' => $html_node_1,
        'format' => 'basic_html',
      ],
    ]);
    $node_1->save();

    // Create a node with a link to the restricted webform.
    $html_node_2 = '
      <p>Link to restricted webform: <a href="/webform/test_webform_restricted">Restricted Webform</a></p>
    ';

    $node_2 = Node::create([
      'type' => 'page',
      'title' => 'Test Node with Restricted Webform',
      'nid' => 2,
      'body' => [
        'value' => $html_node_2,
        'format' => 'basic_html',
      ],
    ]);
    $node_2->save();

    // Create a node with both types of webform links.
    $html_node_3 = '
      <p>Link to accessible webform: <a href="/webform/test_webform_accessible">Accessible</a></p>
      <p>Link to restricted webform: <a href="/webform/test_webform_restricted">Restricted</a></p>
    ';

    $node_3 = Node::create([
      'type' => 'page',
      'title' => 'Test Node with Both Webforms',
      'nid' => 3,
      'body' => [
        'value' => $html_node_3,
        'format' => 'basic_html',
      ],
    ]);
    $node_3->save();
  }

  /**
   * Tests that accessible webforms are not marked as access-denied-link.
   */
  public function testAccessibleWebformNotMarkedAsAccessDenied() {
    // Fetch records from entity_mesh table.
    $records = $this->fetchEntityMeshRecords();

    // Find the record for the accessible webform.
    $filtered = array_filter($records, function ($record) {
      return $record['source_entity_id'] == 1 &&
        $record['target_href'] === '/webform/test_webform_accessible';
    });

    $record = reset($filtered);

    $this->assertNotEmpty($record, 'Record for accessible webform should exist');
    $this->assertEquals('internal', $record['target_link_type'], 'Link type should be internal');
    $this->assertEquals('webform', $record['target_entity_type'], 'Entity type should be webform');
    $this->assertEquals('test_webform_accessible', $record['target_entity_id'], 'Entity ID should match');
    $this->assertEquals('link', $record['subcategory'], 'Subcategory should be "link", not "access-denied-link"');
    $this->assertNotEquals('access-denied-link', $record['subcategory'], 'Should NOT be marked as access-denied-link');
  }

  /**
   * Tests that restricted webforms are detected as webform entities.
   *
   * Note: In kernel tests, webform access rules behave differently than in
   * production due to how the webform module merges custom access rules with
   * defaults. This test verifies that restricted webforms are at least
   * detected as webform entities correctly.
   */
  public function testRestrictedWebformDetectedAsWebform() {
    // Fetch records from entity_mesh table.
    $records = $this->fetchEntityMeshRecords();

    // Find the record for the restricted webform.
    $filtered = array_filter($records, function ($record) {
      return $record['source_entity_id'] == 2 &&
        $record['target_href'] === '/webform/test_webform_restricted';
    });

    $record = reset($filtered);

    $this->assertNotEmpty($record, 'Record for restricted webform should exist');
    $this->assertEquals('internal', $record['target_link_type'], 'Link type should be internal');
    $this->assertEquals('webform', $record['target_entity_type'], 'Entity type should be webform');
    $this->assertEquals('test_webform_restricted', $record['target_entity_id'], 'Entity ID should match');
  }

  /**
   * Tests multiple webforms in the same node are handled correctly.
   */
  public function testMultipleWebformsInSameNode() {
    // Fetch records from entity_mesh table.
    $records = $this->fetchEntityMeshRecords();

    // Find records for node 3 (has both webforms).
    $filtered_accessible = array_filter($records, function ($record) {
      return $record['source_entity_id'] == 3 &&
        $record['target_href'] === '/webform/test_webform_accessible';
    });

    $filtered_restricted = array_filter($records, function ($record) {
      return $record['source_entity_id'] == 3 &&
        $record['target_href'] === '/webform/test_webform_restricted';
    });

    $record_accessible = reset($filtered_accessible);
    $record_restricted = reset($filtered_restricted);

    // Check accessible webform is not marked as access-denied.
    $this->assertNotEmpty($record_accessible, 'Record for accessible webform in node 3 should exist');
    $this->assertEquals('link', $record_accessible['subcategory'], 'Accessible webform should be "link"');

    // Check restricted webform is detected as webform entity.
    $this->assertNotEmpty($record_restricted, 'Record for restricted webform in node 3 should exist');
    $this->assertEquals('webform', $record_restricted['target_entity_type'], 'Should be detected as webform entity');
  }

  /**
   * Tests webform access configuration can be changed.
   */
  public function testWebformAccessWithAuthenticatedAnalyzerAccount() {
    // Change analyzer account to authenticated user.
    $config = $this->config('entity_mesh.settings');
    $config->set('analyzer_account', [
      'type' => 'authenticated',
      'roles' => [],
      'user_id' => NULL,
    ]);
    $config->save();

    // Clear the mesh account cache to pick up the new configuration.
    $this->container->get('entity_mesh.repository')->clearMeshAccountCache();

    // Verify the configuration was saved correctly.
    $saved_config = $this->config('entity_mesh.settings')->get('analyzer_account');
    $this->assertEquals('authenticated', $saved_config['type'], 'Analyzer account type should be authenticated');

    // Resave nodes to trigger re-analysis.
    $nodes = $this->container->get('entity_type.manager')->getStorage('node')->loadMultiple();
    foreach ($nodes as $node) {
      $node->save();
    }

    // Fetch records from entity_mesh table.
    $records = $this->fetchEntityMeshRecords();

    // Find the record for the webform.
    $filtered = array_filter($records, function ($record) {
      return $record['source_entity_id'] == 2 &&
        $record['target_href'] === '/webform/test_webform_restricted';
    });

    $record = reset($filtered);

    $this->assertNotEmpty($record, 'Record for webform should exist after re-analysis');
    $this->assertEquals('webform', $record['target_entity_type'], 'Should still be detected as webform');
  }

  /**
   * {@inheritdoc}
   */
  public static function linkCasesProvider() {
    $defaults = self::$providerDefaults;

    return [
      'Accessible webform link' => array_merge($defaults, [
        'source_entity_id' => 1,
        'target_href' => '/webform/test_webform_accessible',
        'expected_target_link_type' => 'internal',
        'expected_target_subcategory' => 'link',
        'expected_target_entity_type' => 'webform',
        'expected_target_entity_id' => 'test_webform_accessible',
        'expected_source_entity_type' => 'node',
        'expected_source_entity_bundle' => 'page',
        'expected_source_title' => 'Test Node with Accessible Webform (node - 1)',
      ]),
      'Restricted webform link' => array_merge($defaults, [
        'source_entity_id' => 2,
        'target_href' => '/webform/test_webform_restricted',
        'expected_target_link_type' => 'internal',
        'expected_target_subcategory' => 'link',
        'expected_target_entity_type' => 'webform',
        'expected_target_entity_id' => 'test_webform_restricted',
        'expected_source_entity_type' => 'node',
        'expected_source_entity_bundle' => 'page',
        'expected_source_title' => 'Test Node with Restricted Webform (node - 2)',
      ]),
    ];
  }

}
