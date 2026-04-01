<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose\Functional\Core;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\graphql_compose\Functional\GraphQLComposeBrowserTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Test the entity version is loading as expected.
 *
 * @group graphql_compose
 */
class EntityRevisionTest extends GraphQLComposeBrowserTestBase {
  use ContentModerationTestTrait;

  /**
   * The node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $node;

  /**
   * The privileged user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $privilegedUser;

  /**
   * The revision ids.
   *
   * @var array
   */
  protected array $revisionIds = [];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'workflows',
    'content_moderation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createContentType([
      'type' => 'test',
      'name' => 'Node test type',
    ]);

    $workflow = $this->createEditorialWorkflow();
    $this->addEntityTypeAndBundleToWorkflow($workflow, 'node', 'test');

    // Create the initial revision.
    $this->node = $this->createNode([
      'type' => 'test',
      'title' => 'Test',
      'status' => 1,
      'moderation_state' => 'published',
    ]);

    // Store some revisions for testing loading by id.
    $this->revisionIds['A'] = $this->createNodeDraft($this->node, ['title' => 'Test A']);
    $this->revisionIds['B'] = $this->createNodeDraft($this->node, ['title' => 'Test B']);
    $this->revisionIds['C'] = $this->createNodeDraft($this->node, ['title' => 'Test C']);

    // Create the latest working english copy.
    $this->createNodeDraft($this->node, ['title' => 'Test working-copy']);

    $this->setEntityConfig('node', 'test', [
      'enabled' => TRUE,
      'query_load_enabled' => TRUE,
    ]);

    // Create a user with the view all permissions.
    $this->privilegedUser = $this->createUser([
      'access content',
      'view any unpublished content',
      'view latest version',
      ...$this->graphqlPermissions,
    ]);

    node_access_rebuild();
  }

  /**
   * Install the language modules and create some translations.
   */
  private function setupLanguageModules(): void {
    $modules = [
      'content_translation',
      'config_translation',
      'language',
    ];

    $this->container->get('module_installer')->install($modules, TRUE);

    $this->resetAll();

    ConfigurableLanguage::createFromLangcode('ja')->save();
    ConfigurableLanguage::createFromLangcode('de')->save();
    ConfigurableLanguage::createFromLangcode('abc')->save();

    // Enable translations for the test node type.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'test')
      ->setDefaultLangcode(LanguageInterface::LANGCODE_SITE_DEFAULT)
      ->setLanguageAlterable(TRUE)
      ->save();

    $this->node = Node::load($this->node->id());

    // Enable translations for the test node type.
    $this->node->addTranslation('ja', [
      'title' => 'Test (JA)',
    ])->save();

    $this->node->addTranslation('de', [
      'title' => 'Test (DE)',
    ])->save();

    // Create some translation drafts.
    $this->createNodeDraft($this->node->getTranslation('ja'), ['title' => 'Test working-copy (JA)']);

    node_access_rebuild();
  }

  /**
   * Creates a new node revision.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param array $values
   *   The field values.
   *
   * @return int|string|null
   *   The revision id.
   */
  private function createNodeDraft(NodeInterface $node, array $values): int|string|null {
    foreach ($values as $field_name => $value) {
      $node->set($field_name, $value);
    }
    $node->set('moderation_state', 'draft');
    $node->save();

    return $node->getRevisionId();
  }

  /**
   * Test load latest version of entity.
   */
  public function testNodeLoadByLatest(): void {
    $query = <<<GQL
      query {
        node(id: "{$this->node->uuid()}", revision: "current") {
          ... on NodeInterface {
            id
            title
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);
    $this->assertEquals('Test', $content['data']['node']['title']);

    // Enable languages and re-test.
    $this->setupLanguageModules();

    $content = $this->executeQuery($query);
    $this->assertEquals('Test', $content['data']['node']['title']);

    // Login as user with view all revision permission.
    $this->drupalLogin($this->privilegedUser);

    $content = $this->executeQuery($query);
    $this->assertEquals('Test', $content['data']['node']['title']);
  }

  /**
   * Test loading revision by numeric id.
   */
  public function testNodeNumericRevisions(): void {
    foreach ($this->revisionIds as $key => $revisionId) {
      $query = <<<GQL
        query {
          node(id: "{$this->node->uuid()}", revision: {$revisionId}) {
            ... on NodeInterface {
              id
              title
            }
          }
        }
      GQL;

      $content = $this->executeQuery($query);

      // The working copy is not visible to the anonymous user.
      $this->assertNull($content['data']['node']);

      // Login as user with view all revision permission.
      $this->drupalLogin($this->privilegedUser);

      $content = $this->executeQuery($query);
      $this->assertEquals('Test ' . $key, $content['data']['node']['title']);

      $this->drupalLogout();
    }
  }

  /**
   * Tests working copy retrieval.
   */
  public function testNodeLoadByWorkingCopy(): void {
    $query = <<<GQL
      query {
        node(id: "{$this->node->uuid()}", revision: "latest") {
          ... on NodeInterface {
            id
            title
          }
        }
      }
    GQL;

    // The working copy is not visible to the anonymous user.
    $content = $this->executeQuery($query);
    $this->assertNull($content['data']['node']);

    // Login as user with view all revision permission.
    $this->drupalLogin($this->privilegedUser);

    $content = $this->executeQuery($query);
    $this->assertEquals('Test working-copy', $content['data']['node']['title']);

    // Enable languages and re-test.
    $this->setupLanguageModules();

    $content = $this->executeQuery($query);
    $this->assertEquals('Test working-copy', $content['data']['node']['title']);
  }

  /**
   * Test load latest version of entity.
   */
  public function testNodeLoadNoLangByLatest(): void {

    $this->setupLanguageModules();

    $query = <<<GQL
      query {
        node(id: "{$this->node->uuid()}", revision: "current", langcode: "abc") {
          ... on NodeInterface {
            id
            title
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);
    $this->assertNull($content['data']['node']);

    $query = <<<GQL
      query {
        node(id: "{$this->node->uuid()}", revision: "latest", langcode: "abc") {
          ... on NodeInterface {
            id
            title
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);
    $this->assertNull($content['data']['node']);
  }

  /**
   * Test revision loading works with simple queries disabled.
   */
  public function testNodeRevisionLoadNonSimpleQueries(): void {
    $this->setConfig('settings.simple_queries', FALSE);

    $query_current = <<<GQL
      query {
        nodeTest(id: "{$this->node->uuid()}", revision: "current") {
          id
          title
        }
      }
    GQL;

    $query_latest = <<<GQL
      query {
        nodeTest(id: "{$this->node->uuid()}", revision: "latest") {
          id
          title
        }
      }
    GQL;

    $content = $this->executeQuery($query_current);
    $this->assertEquals('Test', $content['data']['nodeTest']['title']);

    // Login as user with view all revision permission.
    $this->drupalLogin($this->privilegedUser);

    $content = $this->executeQuery($query_latest);
    $this->assertEquals('Test working-copy', $content['data']['nodeTest']['title']);
  }

  /**
   * Test incorrect revision id returns null.
   */
  public function testNodeIncorrectRevisionIdReturnsNull(): void {
    $query = <<<GQL
      query {
        node(id: "{$this->node->uuid()}", revision: "90210") {
          ... on NodeInterface {
            id
            title
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);
    $this->assertNull($content['data']['node']);
  }

  /**
   * Test incorrect revision id returns null.
   */
  public function testNodeWrongRevisionIdReturnsNull(): void {

    // Login as user with view all revision permission.
    $this->drupalLogin($this->privilegedUser);

    $new_node = $this->createNode([
      'type' => 'test',
      'title' => 'Bonk',
      'status' => 1,
      'moderation_state' => 'published',
    ]);

    $new_revision_id = $this->createNodeDraft($new_node, ['title' => 'Bunk']);

    // Use the wrong (valid) revision id on the node.
    $query = <<<GQL
      query {
        node(id: "{$this->node->uuid()}", revision: {$new_revision_id}) {
          ... on NodeInterface {
            id
            title
          }
        }
      }
    GQL;

    // Should be an error.
    $content = $this->executeQuery($query);
    $this->assertSame('The requested revision does not belong to the requested entity.', $content['errors'][0]['message']);
    $this->assertNull($content['data']['node']);

    // Test the new node.
    $query = <<<GQL
      query {
        node(id: "{$new_node->uuid()}", revision: {$new_revision_id}) {
          ... on NodeInterface {
            id
            title
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);
    $this->assertEquals('Bunk', $content['data']['node']['title']);
  }

  /**
   * Test latest version retrieval for translated content.
   */
  public function testNodeLoadByLatestWithLangcode(): void {

    $this->setupLanguageModules();

    $query = <<<GQL
      query {
        node(id: "{$this->node->uuid()}", langcode: "ja", revision: "current") {
          ... on NodeInterface {
            id
            title
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);
    $this->assertEquals('Test (JA)', $content['data']['node']['title']);
  }

  /**
   * Test working-copy version retrieval for translated content.
   */
  public function testNodeLoadByWorkingCopyWithLangcode(): void {

    $this->setupLanguageModules();

    $query = <<<GQL
      query {
        node(id: "{$this->node->uuid()}", langcode: "ja", revision: "latest") {
          ... on NodeInterface {
            id
            title
          }
        }
      }
    GQL;

    // Now try as user with view all revision permission.
    $this->drupalLogin($this->privilegedUser);

    $content = $this->executeQuery($query);
    $this->assertEquals('Test working-copy (JA)', $content['data']['node']['title']);
  }

  /**
   * Test the latest translated revision is returned when no working-copy.
   */
  public function testNodeLoadByWorkingCopyWithLangcodeFallback(): void {

    $this->setupLanguageModules();

    $query = <<<GQL
      query {
        node(id: "{$this->node->uuid()}", langcode: "de", revision: "latest") {
          ... on NodeInterface {
            id
            title
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);
    $this->assertEquals('Test (DE)', $content['data']['node']['title']);
  }

}
