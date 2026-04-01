<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose\Functional\Core;

use Drupal\Tests\graphql_compose\Functional\GraphQLComposeBrowserTestBase;
use Drupal\node\NodeInterface;

/**
 * Test UUID load switching.
 *
 * @group graphql_compose
 */
class EntityUuidLoadTest extends GraphQLComposeBrowserTestBase {

  /**
   * The test node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $node;

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
    ]);

    $this->setEntityConfig('node', 'test', [
      'enabled' => TRUE,
      'query_load_enabled' => TRUE,
    ]);

    $this->setFieldConfig('node', 'test', 'body', [
      'enabled' => TRUE,
    ]);
  }

  /**
   * Check entity can load by uuid.
   */
  public function testLoadContentEntity(): void {
    // Try normal load by UUID.
    $query = <<<GQL
      query {
        node(id: "{$this->node->uuid()}") {
          ... on NodeInterface {
            id
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertEquals($this->node->uuid(), $content['data']['node']['id']);

    $this->setConfig('settings.expose_entity_ids', TRUE);

    // Try load by ID.
    $query = <<<GQL
    query {
      node(id: "{$this->node->id()}") {
        ... on NodeInterface {
          id
          uuid
        }
      }
    }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertEquals($this->node->id(), $content['data']['node']['id']);
    $this->assertEquals($this->node->uuid(), $content['data']['node']['uuid']);

    // Try load by UUID again.
    $query = <<<GQL
    query {
      node(id: "{$this->node->uuid()}") {
        ... on NodeInterface {
          id
          uuid
        }
      }
    }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertEquals($this->node->id(), $content['data']['node']['id']);
    $this->assertEquals($this->node->uuid(), $content['data']['node']['uuid']);
  }

}
