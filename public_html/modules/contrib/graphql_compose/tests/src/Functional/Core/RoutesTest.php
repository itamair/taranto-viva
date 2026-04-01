<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose\Functional\Core;

use Drupal\Tests\graphql_compose\Functional\GraphQLComposeBrowserTestBase;
use Drupal\node\NodeInterface;

/**
 * Tests specific to GraphQL Compose routes.
 *
 * @group graphql_compose
 */
class RoutesTest extends GraphQLComposeBrowserTestBase {

  /**
   * The test node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $node;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'graphql_compose_routes',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createContentType([
      'type' => 'test',
      'name' => 'Test node type',
    ]);

    $this->node = $this->createNode([
      'type' => 'test',
      'title' => 'Test',
      'status' => 1,
      'path' => [
        'alias' => '/test',
      ],
    ]);

    $this->setEntityConfig('node', 'test', [
      'enabled' => TRUE,
      'routes_enabled' => TRUE,
    ]);
  }

  /**
   * Test load entity by route.
   */
  public function testRouteLoadByNodeUri(): void {
    $query = <<<GQL
      query {
        route(path: "/node/{$this->node->id()}") {
          ... on RouteInternal {
            entity {
              ... on NodeTest {
                id
              }
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertEquals(
      $content['data']['route']['entity']['id'],
      $this->node->uuid(),
    );
  }

  /**
   * Test load entity by route.
   */
  public function testRouteLoadByAlias(): void {
    $query = <<<GQL
      query {
        route(path: "/test") {
          ... on RouteInternal {
            entity {
              ... on NodeTest {
                id
              }
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertEquals(
      $content['data']['route']['entity']['id'],
      $this->node->uuid(),
    );
  }

}
