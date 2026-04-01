<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose\Functional\Core;

use Drupal\Tests\graphql_compose\Functional\GraphQLComposeBrowserTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;
use Drupal\system\MenuInterface;

/**
 * Tests specific to GraphQL Compose menus.
 *
 * @group graphql_compose
 */
class MenusTest extends GraphQLComposeBrowserTestBase {

  /**
   * The test menu.
   *
   * @var \Drupal\system\MenuInterface
   */
  protected MenuInterface $menu;

  /**
   * The test links.
   *
   * @var \Drupal\menu_link_content\Entity\MenuLinkContent[]
   */
  protected array $links;

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
    'menu_link_content',
    'graphql_compose_menus',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->menu = Menu::create([
      'id' => 'test-menu',
      'label' => 'Test Menu',
    ]);

    $this->menu->save();

    $this->nodes[1] = $this->createNode([
      'title' => 'Test node 1',
    ]);

    $this->nodes[2] = $this->createNode([
      'title' => 'Test node 2',
    ]);

    $this->links[1] = MenuLinkContent::create([
      'title' => 'Test link 1',
      'link' => ['uri' => 'entity:node/' . $this->nodes[1]->id()],
      'menu_name' => $this->menu->id(),
      'weight' => 10,
    ]);

    $this->links[2] = MenuLinkContent::create([
      'title' => 'Test link 2',
      'link' => ['uri' => 'entity:node/' . $this->nodes[2]->id()],
      'menu_name' => $this->menu->id(),
      'weight' => 5,
    ]);

    $this->links[3] = MenuLinkContent::create([
      'title' => 'Test link child',
      'link' => ['uri' => 'entity:node/' . $this->nodes[1]->id()],
      'menu_name' => $this->menu->id(),
      'parent' => $this->links[1]->getPluginId(),
    ]);

    $this->links[4] = MenuLinkContent::create([
      'title' => 'Test disabled',
      'link' => ['uri' => 'internal:/'],
      'menu_name' => $this->menu->id(),
      'weight' => 5,
      'enabled' => FALSE,
    ]);

    $this->links[5] = MenuLinkContent::create([
      'title' => 'Test external',
      'link' => ['uri' => 'https://www.google.com'],
      'menu_name' => $this->menu->id(),
      'weight' => 6,
    ]);

    foreach ($this->links as $link) {
      $link->save();
    }

    $this->setEntityConfig('menu', 'test-menu', [
      'enabled' => TRUE,
    ]);
  }

  /**
   * Test menu links by name.
   */
  public function testMenuLoadByName(): void {
    $query = <<<GQL
      query {
        menu(name: TEST_MENU) {
          id
          name
          items {
            title
            url
            internal
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $menu = $content['data']['menu'];

    $this->assertEquals($this->menu->uuid(), $menu['id']);
    $this->assertEquals($this->menu->label(), $menu['name']);

    $this->assertCount(3, $menu['items']);

    // Sort weight sorting.
    $this->assertEquals('Test link 2', $menu['items'][0]['title']);
    $this->assertEquals('Test external', $menu['items'][1]['title']);
    $this->assertEquals('Test link 1', $menu['items'][2]['title']);

    // Test internal link.
    $this->assertTrue($menu['items'][0]['internal']);
    $this->assertFalse($menu['items'][1]['internal']);
  }

  /**
   * Test menu link parents.
   */
  public function testMenuParents(): void {

    $query = <<<GQL
      query {
        menu(name: TEST_MENU) {
          items {
            title
            children {
              title
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $menu = $content['data']['menu'];

    $this->assertEmpty($menu['items'][0]['children']);

    $this->assertEquals('Test link child', $menu['items'][2]['children'][0]['title']);
  }

}
