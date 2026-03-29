<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose\Functional\Contrib;

use Drupal\Tests\graphql_compose\Functional\GraphQLComposeBrowserTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\views\Entity\View;
use Drupal\views\Tests\ViewResultAssertionTrait;
use Drupal\views\Tests\ViewTestData;

/**
 * Views Search API tests.
 *
 * @group graphql_compose
 */
class ViewsSearchApiTest extends GraphQLComposeBrowserTestBase {

  use ViewResultAssertionTrait;

  /**
   * We aren't concerned with strict config schema for contrib.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE; // @phpcs:ignore

  /**
   * The index id.
   *
   * @var string
   */
  protected $indexId = 'default_index';

  /**
   * The test nodes.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  protected array $nodes;

  /**
   * The search strings keyed by node id.
   *
   * @var array
   */
  protected array $searchStrings = [];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'path_alias',
    'system',
    'views',
    'user',
    'node',
    'block',
    'language',
    'search_api',
    'search_api_db',
    'graphql_compose_views',
    'graphql_compose_test_views',
  ];

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['graphql_compose_search_api_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    \Drupal::state()->set('search_api_use_tracking_batch', FALSE);

    // Create the node type.
    $this->createContentType(['type' => 'test']);

    // Create some nodes.
    $nodes = [];
    foreach (range(1, 5) as $i) {

      $this->searchStrings[$i] = uniqid('UniqueText') . 'Test';

      $nodes[] = $this->createNode([
        'type' => 'test',
        'title' => 'The node ' . $i,
        'body' => [
          [
            'value' => 'The node body ' . $this->searchStrings[$i],
            'format' => 'plain_text',
          ],
        ],
      ]);

      // Sleep for 1ms to ensure created order.
      usleep(1000);
    }

    $this->nodes = $nodes;
    $this->nodes[0]->set('sticky', TRUE);
    $this->nodes[0]->save();

    // Import the views config.
    ViewTestData::createTestViews(static::class, ['graphql_compose_test_views']);

    // Index the nodes.
    $index = Index::load($this->indexId);

    \Drupal::getContainer()
      ->get('search_api.index_task_manager')
      ->addItemsAll($index);

    $index->indexItems();

    // Setup GraphQL Compose.
    $this->setEntityConfig('node', 'test', [
      'enabled' => TRUE,
    ]);

    $this->setFieldConfig('node', 'test', 'body', [
      'enabled' => TRUE,
    ]);

    $this->rebuildContainer();
  }

  /**
   * Test the entity results of a search API view.
   */
  public function testSearchApiEntityView(): void {

    $view = View::load('graphql_compose_search_api_test');

    $query = <<<GQL
      query {
        testEntityNode {
          id
          view
          display
          langcode
          label
          description
          pageInfo {
            offset
            page
            pageSize
            total
          }
          results {
            ... on NodeTest {
              id
              title
              body {
                processed
              }
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertArrayHasKey('testEntityNode', $content['data']);
    $data = $content['data']['testEntityNode'];

    $this->assertEquals($view->uuid(), $data['id']);
    $this->assertEquals($view->id(), $data['view']);
    $this->assertEquals('graphql_entity', $data['display']);
    $this->assertEquals($view->label(), $data['label']);

    $info = $data['pageInfo'];
    $this->assertEquals(0, $info['offset']);
    $this->assertEquals(0, $info['page']);
    $this->assertEquals(10, $info['pageSize']);
    $this->assertEquals(5, $info['total']);

    $this->assertCount(5, $data['results']);

    $this->assertEquals('The node 1', $data['results'][0]['title']);
    $this->assertStringContainsString('The node body', $data['results'][0]['body']['processed']);
  }

  /**
   * Ensure the layout teaser is the default.
   */
  public function testSearchApiFieldView(): void {

    $query = <<<GQL
      query {
        testFieldNode {
          results {
            title
            nid
            sticky
            created
            body
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertArrayHasKey('testFieldNode', $content['data']);
    $data = $content['data']['testFieldNode'];

    $sample = $data['results'][0];

    $this->assertStringContainsString('The node 1', $sample['title']);
    $this->assertIsInt($sample['nid']);
    $this->assertIsBool($sample['sticky']);
    $this->assertIsNumeric($sample['created']);
    $this->assertStringContainsString('The node body', $sample['body']);
    $this->assertStringContainsString($this->searchStrings[$sample['nid']], $sample['body']);
  }

  /**
   * Filter by sticky true.
   */
  public function testSearchApiEntityFilterView(): void {
    $query = <<<GQL
      query {
        testEntityNode(filter: { sticky: true }) {
          results {
            ... on NodeTest {
              id
              sticky
            }
          }
        }
      }
    GQL;
    $content = $this->executeQuery($query);

    $this->assertCount(1, $content['data']['testEntityNode']['results']);
    $this->assertTrue($content['data']['testEntityNode']['results'][0]['sticky']);
  }

  /**
   * Filter by search full text.
   */
  public function testSearchApiEntityFilterFulltext(): void {
    $query = <<<GQL
      query(\$text: String!) {
        testEntityNode(filter: { search_api_fulltext: \$text }) {
          results {
            ... on NodeTest {
              id
              title
              body {
                processed
              }
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query, [
      'text' => $this->searchStrings[2],
    ]);

    $results = $content['data']['testEntityNode']['results'];

    $this->assertCount(1, $results);
    $this->assertStringContainsString($this->searchStrings[2], $results[0]['body']['processed']);
  }

  /**
   * Test multiple bundles are supported.
   */
  public function testSearchApiMultipleBundles(): void {
    $this->createContentType(['type' => 'test_two']);

    $this->setEntityConfig('node', 'test_two', [
      'enabled' => TRUE,
    ]);

    $this->setFieldConfig('node', 'test_two', 'body', [
      'enabled' => TRUE,
    ]);

    $this->nodes[] = $this->createNode([
      'type' => 'test_two',
      'title' => 'The second node',
      'body' => [
        [
          'value' => 'The node body',
          'format' => 'plain_text',
        ],
      ],
    ]);

    // Index the nodes.
    $index = Index::load($this->indexId);

    \Drupal::getContainer()
      ->get('search_api.index_task_manager')
      ->addItemsAll($index);

    $index->indexItems();

    $query = <<<GQL
      query {
        testEntityNode {
          id
          results {
            __typename
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $results = $content['data']['testEntityNode']['results'];
    $this->assertCount(6, $results);

    $types = array_column($results, '__typename');
    $this->assertContains('NodeTest', $types);
    $this->assertContains('NodeTestTwo', $types);
  }

  /**
   * Test multiple bundles are supported, if not enabled.
   */
  public function testSearchApiMultipleBundlesDisabled(): void {
    $this->createContentType(['type' => 'test_two']);

    $this->createNode([
      'type' => 'test_two',
      'title' => 'The second node',
      'body' => [
        [
          'value' => 'The node body',
          'format' => 'plain_text',
        ],
      ],
    ]);

    // Index the nodes.
    $index = Index::load($this->indexId);

    \Drupal::getContainer()
      ->get('search_api.index_task_manager')
      ->addItemsAll($index);

    $index->indexItems();

    $query = <<<GQL
      query {
        testEntityNode {
          id
          results {
            __typename
            ... on NodeInterface {
              id
              title
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertStringContainsStringIgnoringCase(
      'Entity type node::test_two is not enabled',
      $content['errors'][0]['message']
    );
  }

}
