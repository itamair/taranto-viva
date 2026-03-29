<?php

namespace Drupal\Tests\entity_mesh\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\filter\Entity\FilterFormat;
use Drupal\node\Entity\Node;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;

/**
 * Tests entity_mesh permissions handling.
 *
 * @group entity_mesh
 */
class EntityMeshPermissionsTest extends KernelTestBase {

  use ContentTypeCreationTrait;
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
    $this->installSchema('entity_mesh', ['entity_mesh']);
    $this->installConfig(['filter', 'node', 'system', 'language', 'entity_mesh']);
    $this->installSchema('node', ['node_access']);

    $this->createContentType(['type' => 'page', 'name' => 'Page']);

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

    if (!Role::load(AccountInterface::AUTHENTICATED_ROLE)) {
      Role::create(['id' => AccountInterface::AUTHENTICATED_ROLE, 'label' => 'Authenticated user'])->save();
    }

    // Create a custom role for testing.
    if (!Role::load('editor')) {
      Role::create(['id' => 'editor', 'label' => 'Editor'])->save();
    }

    // Create a custom role for testing.
    if (!Role::load('redactor')) {
      Role::create(['id' => 'redactor', 'label' => 'Redactor'])->save();
    }

    $this->grantPermissions(Role::load(AccountInterface::ANONYMOUS_ROLE), [
      'access content',
    ]);

    $this->grantPermissions(Role::load(AccountInterface::AUTHENTICATED_ROLE), [
      'access content',
      'bypass node access',
    ]);

    $this->grantPermissions(Role::load('editor'), [
      'access content',
      'bypass node access',
    ]);
  }

  /**
   * Tests that entities without anonymous access are excluded from analysis.
   */
  public function testEntitiesWithoutAnonymousAccessAreExcluded() {

    $html_node = '
      <p>External link https schema: <a href="https://example.com">Example</a></p>
      <p>External link http schema: <a href="http://example.com">Example</a></p>
      <p>Internal broken link: <a href="/non-existent">Broken</a></p>
      <p>Iframe content: <iframe src="https://example.com/iframe"></iframe></p>
      <p>Mailto link: <a href="mailto:hola@metadrop.net">Mailto link</a></p>
      <p>Tel link: <a href="tel:+34654654654">Tel link</a></p>
    ';

    // Create an unpublished node that anonymous users cannot access.
    $restricted_node = Node::create([
      'type' => 'page',
      'title' => 'Unpublished Node (No Anonymous Access)',
    // Owner user ID.
      'uid' => 1,
    // Unpublished.
      'status' => 0,
      'body' => [
        'value' => $html_node,
        'format' => 'basic_html',
      ],
    ]);
    $restricted_node->save();

    // Create a published node that anonymous users can access.
    $public_node = Node::create([
      'type' => 'page',
      'title' => 'Published Node (Anonymous Access)',
    // Owner user ID.
      'uid' => 1,
    // Published.
      'status' => 1,
      'body' => [
        'value' => $html_node,
        'format' => 'basic_html',
      ],
    ]);

    $public_node->save();

    // Verify anonymous user can access public node but not restricted.
    $anonymous = $this->container->get('entity_type.manager')->getStorage('user')->load(0);

    // Confirm access permissions.
    $this->assertTrue($public_node->access('view', $anonymous), 'Anonymous user should have access to public node');
    $this->assertFalse($restricted_node->access('view', $anonymous), 'Anonymous user should NOT have access to restricted node');

    // Update nodes to trigger entity_mesh processing via hook_entity_update.
    // Entity mesh processes entities on insert/update/delete hooks.
    $restricted_node->setTitle('Updated: ' . $restricted_node->getTitle());
    $restricted_node->save();

    $public_node->setTitle('Updated: ' . $public_node->getTitle());
    $public_node->save();

    // Query the entity_mesh table to check which nodes were processed.
    $database = $this->container->get('database');

    // Get all sources from the repository.
    $query = $database->select('entity_mesh', 'em')
      ->fields('em', ['source_entity_type', 'source_entity_id']);
    $results = $query->execute()->fetchAll();

    // Convert results to a more usable format.
    $processed_entity_ids = [];
    foreach ($results as $result) {
      $processed_entity_ids[] = $result->source_entity_id;
    }

    // Assert that the public node was processed.
    $this->assertContains($public_node->id(), $processed_entity_ids, 'Public node should be included in entity_mesh analysis.');

    // Assert that the restricted node was NOT processed
    // (this test should fail initially).
    $this->assertNotContains($restricted_node->id(), $processed_entity_ids, 'Restricted node should be excluded from entity_mesh analysis.');
  }

  /**
   * Test to verify access by configuring a specific role.
   */
  public function testEntitiesWithSpecificRoleAccess() {
    // Configure entity mesh to analyze as authenticated user with editor role.
    $config = $this->config('entity_mesh.settings');
    $config->set('analyzer_account', [
      'type' => 'authenticated',
      'roles' => ['editor'],
      'user_id' => NULL,
    ]);
    $config->save();

    // Clear the repository service cache.
    \Drupal::service('entity_mesh.repository')->clearMeshAccountCache();

    // Create a user with editor user.
    $editor_user = $this->createUser(['access content', 'bypass node access']);
    $editor_user->addRole('editor');
    $editor_user->save();

    $this->baseCheckEditorAccess($editor_user);
  }

  /**
   * Test to verify access by configuring a specific user.
   */
  public function testEntitiesWithSpecificUser() {
    // Create a user with editor role.
    $editor_user = $this->createUser(['access content', 'bypass node access']);
    $editor_user->addRole('editor');
    $editor_user->setUsername('test_editor_user');
    $editor_user->save();

    // Configure entity mesh to analyze as authenticated user with editor role.
    $config = $this->config('entity_mesh.settings');
    $config->set('analyzer_account', [
      'type' => 'user',
      'roles' => NULL,
      'user_id' => (int) $editor_user->id(),
    ]);
    $config->save();

    // Clear the repository service cache.
    \Drupal::service('entity_mesh.repository')->clearMeshAccountCache();

    $this->baseCheckEditorAccess($editor_user);
  }

  /**
   * Tests entities accessible by users with specific roles.
   */
  protected function baseCheckEditorAccess($editor_user) {

    $use_cases = $this->createUseCases();
    $unpublished_node = $use_cases['unpublished_node'];
    $published_node = $use_cases['published_node'];

    // Create an anonymous user.
    $anonymous = $this->container->get('entity_type.manager')->getStorage('user')->load(0);

    // Node only accessible by editor.
    $this->assertTrue($unpublished_node->access('view', $editor_user), 'Editor user should have access to unpublished node');
    $this->assertFalse($unpublished_node->access('view', $anonymous), 'Anonymous user should NOT have access to unpublished node');

    // Node accessible by editor and anonymous.
    $this->assertTrue($published_node->access('view', $anonymous), 'Anonymous user should have access to published node');
    $this->assertTrue($published_node->access('view', $editor_user), 'Editor user should have access to published node');

    // Trigger entity_mesh processing.
    $unpublished_node->setTitle('Updated: ' . $unpublished_node->getTitle());
    $unpublished_node->save();

    $published_node->setTitle('Updated: ' . $published_node->getTitle());
    $published_node->save();

    // Query the entity_mesh table.
    $database = $this->container->get('database');
    $query = $database->select('entity_mesh', 'em')
      ->fields('em', [
        'source_entity_type',
        'source_entity_id',
        'target_entity_id',
        'target_entity_type',
        'subcategory',
        'target_link_type',
      ]);
    $results = $query->execute()->fetchAll();

    $link_accessible_to_unpublished_node = FALSE;

    $processed_entity_ids = [];
    foreach ($results as $result) {
      $processed_entity_ids[] = $result->source_entity_id;
      if ($result->source_entity_id === $published_node->id() &&
        $result->target_entity_id === $unpublished_node->id() &&
        $result->target_link_type === 'internal' &&
        $result->subcategory === 'link'
      ) {
        $link_accessible_to_unpublished_node = TRUE;
      }
    }

    // Assert that the editor accessible node was processed.
    $this->assertContains($published_node->id(), $processed_entity_ids, 'Editor and Anonymous accessible node should be included in entity_mesh analysis.');
    $this->assertContains($unpublished_node->id(), $processed_entity_ids, 'Editor accessible node should be included in entity_mesh analysis.');

    // Check that appears as accessible the unpublish node.
    $this->assertTrue($link_accessible_to_unpublished_node, 'Link to unpublished node should be included in entity_mesh analysis as accessible.');
  }

  /**
   * Creates test use cases with specific permissions.
   *
   * @return array
   *   An array containing the created nodes and their IDs.
   */
  protected function createUseCases(): array {
    // First create the unpublished node with specific ID and external links.
    $unpublished_node_html = '
      <p>External link https schema: <a href="https://example.com">Example External</a></p>
      <p>External link http schema: <a href="http://external-site.com">HTTP External</a></p>
      <p>Mailto link: <a href="mailto:contact@example.com">Email Contact</a></p>
      <p>Tel link: <a href="tel:+34123456789">Phone Contact</a></p>
      <p>Iframe content: <iframe src="https://embedded.com/iframe"></iframe></p>
    ';

    // Create a redactor user with editor role.
    $redactor_user = $this->createUser(['access content']);
    $redactor_user->addRole('redactor');
    $redactor_user->setUsername('test_redactor_user');
    $redactor_user->save();

    $unpublished_node = Node::create([
      'type' => 'page',
      'title' => 'Unpublished Node with External Links',
      'uid' => $redactor_user->id(),
      'status' => 0,
      'body' => [
        'value' => $unpublished_node_html,
        'format' => 'basic_html',
      ],
    ]);
    $unpublished_node->save();
    $unpublished_node_id = $unpublished_node->id();

    // Create the published node that links to the unpublished node.
    $published_node_html = sprintf('
      <p>This is a published node with content.</p>
      <p>Link to unpublished content: <a href="/node/%d">Unpublished Node Link</a></p>
      <p>Another internal link: <a href="/node/%d">Link to same unpublished node</a></p>
      <p>Some regular content here.</p>
    ', $unpublished_node_id, $unpublished_node_id);

    $published_node = Node::create([
      'type' => 'page',
      'title' => 'Published Node with Link to Unpublished',
      'uid' => 1,
      'status' => 1,
      'body' => [
        'value' => $published_node_html,
        'format' => 'basic_html',
      ],
    ]);
    $published_node->save();
    $published_node_id = $published_node->id();

    return [
      'unpublished_node' => $unpublished_node,
      'unpublished_node_id' => $unpublished_node_id,
      'published_node' => $published_node,
      'published_node_id' => $published_node_id,
    ];
  }

}
