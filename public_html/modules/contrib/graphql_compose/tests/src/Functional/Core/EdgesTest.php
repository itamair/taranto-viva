<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose\Functional\Core;

use Drupal\Tests\graphql_compose\Functional\GraphQLComposeBrowserTestBase;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\VocabularyInterface;

/**
 * Tests specific to GraphQL Compose entity edge: Node.
 *
 * @group graphql_compose
 */
class EdgesTest extends GraphQLComposeBrowserTestBase {

  use TaxonomyTestTrait;

  /**
   * The test node.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  protected array $nodes;

  /**
   * The test vocab.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected VocabularyInterface $vocabulary;

  /**
   * The test terms.
   *
   * @var \Drupal\taxonomy\TermInterface[]
   */
  protected array $terms;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_translation',
    'taxonomy',
    'graphql_compose_edges',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    ConfigurableLanguage::create([
      'id' => 'ja',
      'weight' => 1,
    ])->save();

    $this->createContentType([
      'type' => 'test',
      'name' => 'Test node type',
    ]);

    $this->createContentType([
      'type' => 'test_alt',
      'name' => 'Test Alt node type',
    ]);

    $this->nodes[1] = $this->createNode([
      'type' => 'test',
      'title' => 'Test 1',
      'status' => 1,
    ]);

    $this->nodes[2] = $this->createNode([
      'type' => 'test',
      'title' => 'Test 2',
      'status' => 1,
    ]);

    $this->nodes[3] = $this->createNode([
      'type' => 'test_alt',
      'title' => 'Test 3',
      'status' => 1,
    ]);

    $this->nodes[4] = $this->createNode([
      'type' => 'test',
      'title' => 'Japanese',
      'status' => 1,
      'langcode' => 'ja',
    ]);

    $this->nodes[5] = $this->createNode([
      'type' => 'test',
      'title' => 'Unpublished',
      'status' => 0,
    ]);

    $this->setEntityConfig('node', 'test', [
      'enabled' => TRUE,
      'edges_enabled' => TRUE,
    ]);

    $this->setEntityConfig('node', 'test_alt', [
      'enabled' => TRUE,
      'edges_enabled' => TRUE,
    ]);

    $this->vocabulary = Vocabulary::create([
      'name' => 'Test',
      'vid' => 'test',
    ]);

    $this->vocabulary->save();

    $this->terms[1] = $this->createTerm($this->vocabulary, [
      'name' => 'Test term A',
      'weight' => 99,
    ]);

    $this->terms[2] = $this->createTerm($this->vocabulary, [
      'name' => 'Test term B',
      'weight' => 100,
    ]);

    $this->terms[3] = $this->createTerm($this->vocabulary, [
      'name' => 'Test term C',
      'weight' => 98,
    ]);

    $this->setEntityConfig('taxonomy_term', 'test', [
      'enabled' => TRUE,
      'edges_enabled' => TRUE,
    ]);
  }

  /**
   * Check results are expected order.
   */
  public function testEdgeLoad(): void {
    $query = <<<GQL
      query {
        nodeTests(first: 10) {
          nodes {
            __typename
            title
            status
            langcode {
              id
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertNotEmpty($content['data']['nodeTests']['nodes']);

    $this->assertEquals([
      $content['data']['nodeTests']['nodes'][0]['title'],
      $content['data']['nodeTests']['nodes'][1]['title'],
    ], [
      $this->nodes[1]->label(),
      $this->nodes[2]->label(),
    ]);

    // Check only one type loads.
    $types = array_map(
      fn ($row) => $row['__typename'],
      $content['data']['nodeTests']['nodes']
    );

    $types = array_unique($types);

    $this->assertEquals(
      ['NodeTest'],
      $types
    );

    // Ensure only published content.
    $unpublished = array_filter(
      $content['data']['nodeTests']['nodes'],
      fn ($row) => !$row['status'],
    );

    $this->assertEmpty($unpublished);

    // Count should be 3, 2xen 1xja.
    $this->assertCount(3, $content['data']['nodeTests']['nodes']);

    // Double check languages.
    $languages = array_map(
      fn ($row) => $row['langcode']['id'],
      $content['data']['nodeTests']['nodes'],
    );

    $this->assertContains('en', $languages);
    $this->assertContains('ja', $languages);
  }

  /**
   * Check results are expected order (reverse).
   */
  public function testEdgeLoadReverse(): void {
    $query = <<<GQL
      query {
        nodeTests(first: 10, reverse: true, langcode: "en") {
          nodes {
            title
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertNotEmpty($content['data']['nodeTests']['nodes']);

    $this->assertEquals([
      $this->nodes[2]->label(),
      $this->nodes[1]->label(),
    ], [
      $content['data']['nodeTests']['nodes'][0]['title'],
      $content['data']['nodeTests']['nodes'][1]['title'],
    ]);
  }

  /**
   * Check cursors go fwd and back.
   */
  public function testEdgeCursors(): void {

    // First page.
    $query = <<<GQL
      query {
        nodeTests(first: 1) {
          nodes {
            title
          }
          pageInfo {
            endCursor
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertNotEmpty(
      $content['data']['nodeTests']['pageInfo']['endCursor']
    );

    $this->assertEquals(
      $this->nodes[1]->label(),
      $content['data']['nodeTests']['nodes'][0]['title']
    );

    $endCursor = $content['data']['nodeTests']['pageInfo']['endCursor'];

    // Second page.
    $query = <<<GQL
      query {
        nodeTests(first: 1, after: "{$endCursor}", langcode: "en") {
          nodes {
            title
          }
          pageInfo {
            startCursor
            hasNextPage
            hasPreviousPage
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertEquals(
      $this->nodes[2]->label(),
      $content['data']['nodeTests']['nodes'][0]['title']
    );

    // The last item should be hidden as unpublished.
    $this->assertFalse(
      $content['data']['nodeTests']['pageInfo']['hasNextPage']
    );

    $this->assertTrue(
      $content['data']['nodeTests']['pageInfo']['hasPreviousPage']
    );

    $startCursor = $content['data']['nodeTests']['pageInfo']['startCursor'];

    // And back to first page.
    $query = <<<GQL
      query {
        nodeTests(last: 1, before: "{$startCursor}", langcode: "en") {
          nodes {
            title
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertEquals(
      $this->nodes[1]->label(),
      $content['data']['nodeTests']['nodes'][0]['title']
    );
  }

  /**
   * Test language filtering.
   */
  public function testEdgeLoadByLangcode(): void {

    $query = <<<GQL
      query {
        nodeTests(first: 1, langcode: "ja") {
          nodes {
            title
          }
          pageInfo {
            hasNextPage
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertEquals(
      $this->nodes[4]->label(),
      $content['data']['nodeTests']['nodes'][0]['title']
    );

    $this->assertFalse(
      $content['data']['nodeTests']['pageInfo']['hasNextPage']
    );
  }

  /**
   * Test that max limit is enforced.
   */
  public function testEdgeOutOfRange(): void {
    $query = <<<GQL
      query {
        nodeTests(first: 500) {
          nodes {
            title
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertStringContainsStringIgnoringCase(
      'First may not be larger than 100.',
      $content['errors'][0]['message']
    );

    // Lower.
    $config = $this->config('graphql_compose.settings');
    $config->set('settings.edge_max_limit', 10);
    $config->save();

    $query = <<<GQL
      query {
        nodeTests(first: 11) {
          nodes {
            title
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertStringContainsStringIgnoringCase(
      'First may not be larger than 10.',
      $content['errors'][0]['message']
    );

    $query = <<<GQL
      query {
        nodeTests(first: 10) {
          nodes {
            title
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertArrayNotHasKey('errors', $content);

  }

  /**
   * Check the cache list is added.
   */
  public function testEdgeCache(): void {
    $query = <<<GQL
      query {
        nodeTests(first: 10) {
          nodes {
            __typename
          }
        }
      }
    GQL;

    $response = $this->getResponse($query);
    $headers = $response->getHeaders();

    $this->assertArrayHasKey('X-Drupal-Cache-Tags', $headers);
    $tags = explode(' ', $headers['X-Drupal-Cache-Tags'][0]);
    $this->assertContains('node_list', $tags);
  }

  /**
   * Test that the sort order is respected.
   */
  public function testSortTermsByLabel(): void {
    $query = <<<GQL
      query {
        termTests(first: 10, sortKey: TITLE) {
          nodes {
            name
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $titles = array_map(
      fn ($row) => $row['name'],
      $content['data']['termTests']['nodes']
    );

    $this->assertEquals([
      'Test term A',
      'Test term B',
      'Test term C',
    ], $titles);

    // Reverse it.
    $query = <<<GQL
      query {
        termTests(first: 10, sortKey: TITLE, reverse: true) {
          nodes {
            name
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $titles = array_map(
      fn ($row) => $row['name'],
      $content['data']['termTests']['nodes']
    );

    $this->assertEquals([
      'Test term C',
      'Test term B',
      'Test term A',
    ], $titles);
  }

  /**
   * Test that the sort order is respected.
   */
  public function testSortTermsByWeight(): void {
    $query = <<<GQL
      query {
        termTests(first: 10, sortKey: WEIGHT) {
          nodes {
            name
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $titles = array_map(
      fn ($row) => $row['name'],
      $content['data']['termTests']['nodes']
    );

    $this->assertEquals([
      'Test term C',
      'Test term A',
      'Test term B',
    ], $titles);

    // Reverse it.
    $query = <<<GQL
      query {
        termTests(first: 10, sortKey: WEIGHT, reverse: true) {
          nodes {
            name
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $titles = array_map(
      fn ($row) => $row['name'],
      $content['data']['termTests']['nodes']
    );

    $this->assertEquals([
      'Test term B',
      'Test term A',
      'Test term C',
    ], $titles);
  }

}
