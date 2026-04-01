<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose\Functional\Core;

use Drupal\Tests\graphql_compose\Functional\GraphQLComposeBrowserTestBase;
use Drupal\views\Entity\View;
use Drupal\views\Tests\ViewResultAssertionTrait;
use Drupal\views\Tests\ViewTestData;

/**
 * Views tests.
 *
 * @group graphql_compose
 */
class ViewsTest extends GraphQLComposeBrowserTestBase {

  use ViewResultAssertionTrait;

  /**
   * The test nodes.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  protected array $nodes;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'path_alias',
    'system',
    'views',
    'user',
    'node',
    'graphql_compose_test_views',
    'graphql_compose_views',
  ];

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['graphql_compose_node_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create the node type.
    $this->createContentType(['type' => 'test']);
    $this->createContentType(['type' => 'another_type']);

    // Create some nodes.
    $nodes = [];
    foreach (range(1, 5) as $i) {
      $nodes[] = $this->createNode([
        'type' => 'test',
        'title' => 'The node ' . $i,
        'body' => [
          [
            'value' => 'The node body',
            'format' => 'plain_text',
          ],
        ],
      ]);

      // Sleep for 1ms to ensure created order.
      usleep(1000);
    }

    $nodes[] = $this->createNode([
      'type' => 'test',
      'title' => 'The node unpublished',
      'status' => FALSE,
      'body' => [
        [
          'value' => 'The node body',
          'format' => 'plain_text',
        ],
      ],
    ]);

    // Sleep for 1ms to ensure created order.
    usleep(1000);

    $nodes[] = $this->createNode([
      'type' => 'another_type',
      'title' => 'The node unsupported',
      'body' => [
        [
          'value' => 'The unsupported body',
          'format' => 'plain_text',
        ],
      ],
    ]);

    $this->nodes = $nodes;

    $this->nodes[0]->set('sticky', TRUE);
    $this->nodes[0]->save();

    // Import the views config.
    ViewTestData::createTestViews(static::class, ['graphql_compose_test_views']);

    // Setup GraphQL Compose.
    $this->setEntityConfig('node', 'test', [
      'enabled' => TRUE,
    ]);

    $this->setFieldConfig('node', 'test', 'body', [
      'enabled' => TRUE,
    ]);
  }

  /**
   * Ensure the layout teaser is the default.
   */
  public function testSimpleEntityView(): void {

    $view = View::load('graphql_compose_node_test');

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
    $this->assertEquals('test_entity_node', $data['display']);
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
   * Filter by sticky true.
   */
  public function testSimpleEntityFilterView(): void {
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
   * Ensure the layout teaser is the default.
   */
  public function testSimpleFieldView(): void {

    $query = <<<GQL
      query {
        testFieldNode {
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
            title
            nid
            sticky
            created
            bodyAlias
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
    $this->assertStringContainsString('The node body', $sample['bodyAlias']);

    $this->assertTrue($data['results'][0]['sticky']);
    $this->assertFalse($data['results'][1]['sticky']);
  }

  /**
   * Ensure the filters are working.
   */
  public function testSimpleEntityFilterConfig(): void {
    $query = <<<GQL
      query {
        testEntityNode {
          filters {
            id
            plugin
            operator
            type
            label
            description
            required
            multiple
            value
            attributes
            options
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);
    $filters = $content['data']['testEntityNode']['filters'];
    $this->assertCount(1, $filters);

    $filter = $filters[0];

    $this->assertEquals('sticky', $filter['id']);
    $this->assertEquals('select', $filter['type']);
    $this->assertEquals('Sticky status', $filter['label']);
    $this->assertEquals('=', $filter['operator']);

    $this->assertNull($filter['description']);
    $this->assertFalse($filter['required']);
    $this->assertFalse($filter['multiple']);

    $this->assertEquals('All', $filter['value']);

    $this->assertCount(3, $filter['options']);
    $this->assertEquals('No', $filter['options']['0']);
    $this->assertEquals('Yes', $filter['options']['1']);
    $this->assertEquals('- Any -', $filter['options']['All']);
  }

}
