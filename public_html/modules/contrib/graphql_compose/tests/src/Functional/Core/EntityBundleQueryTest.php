<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose\Functional\Core;

use Drupal\Tests\graphql_compose\Functional\GraphQLComposeBrowserTestBase;
use Drupal\node\NodeInterface;

/**
 * Check if extended bundle queries work.
 *
 * @group graphql_compose
 */
class EntityBundleQueryTest extends GraphQLComposeBrowserTestBase {

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

    $this->setConfig('settings.simple_queries', FALSE);

    $this->createContentType([
      'type' => 'test',
      'name' => 'Test node type',
    ]);

    $this->createContentType([
      'type' => 'ding',
      'name' => 'Ding node type',
    ]);

    $this->node = $this->createNode([
      'type' => 'test',
      'title' => 'Test',
      'body' => [
        'value' => 'Test content',
        'format' => 'plain_text',
      ],
      'status' => 1,
      'promote' => 1,
      'sticky' => 0,
      'langcode' => 'en',
    ]);

    $this->setEntityConfig('node', 'test', [
      'enabled' => TRUE,
      'query_load_enabled' => TRUE,
    ]);

    $this->setEntityConfig('node', 'ding', [
      'enabled' => TRUE,
      'query_load_enabled' => TRUE,
    ]);

    $this->setFieldConfig('node', 'test', 'body', [
      'enabled' => TRUE,
    ]);
  }

  /**
   * Test load entity by id.
   */
  public function testNodeLoadByUuid(): void {
    $query = <<<GQL
      query {
        nodeTest(id: "{$this->node->uuid()}") {
          id
          body {
            value
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertNotEmpty($content['data']['nodeTest']);
    $node = $content['data']['nodeTest'];

    $this->assertEquals($this->node->uuid(), $node['id']);
    $this->assertEquals('Test content', $node['body']['value']);
  }

  /**
   * Check that a bundle not exposed wont load by UUID in either mode.
   */
  public function testNodeBundleNotAllowed(): void {

    $this->setEntityConfig('node', 'test', [
      'query_load_enabled' => FALSE,
    ]);

    $query = <<<GQL
      query {
        nodeTest(id: "{$this->node->uuid()}") {
          id
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $this->assertStringContainsStringIgnoringCase(
      'Cannot query field "nodeTest"',
      $content['errors'][0]['message']
    );

    // Switch to "simple" mode.
    $this->setConfig('settings.simple_queries', TRUE);

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
    $this->assertNull($content['data']['node']);

  }

}
