<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose\Functional\Contrib;

use Drupal\Tests\graphql_compose\Functional\GraphQLComposeBrowserTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;

/**
 * Tests specific to GraphQL Compose menus with menu item extras.
 *
 * @group legacy
 */
class MenuItemExtrasTest extends GraphQLComposeBrowserTestBase {

  /**
   * We aren't concerned with strict config schema for contrib.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE; // @phpcs:ignore

  /**
   * The test menu.
   *
   * @var \Drupal\system\MenuInterface[]
   */
  protected array $menus;

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
    'menu_item_extras',
    'graphql_compose_menus',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->nodes[1] = $this->createNode([
      'title' => 'Test node 1',
    ]);

    $this->nodes[2] = $this->createNode([
      'title' => 'Test node 2',
    ]);

    $this->menus[1] = Menu::create([
      'id' => 'test_with',
      'label' => 'Test Menu with fields',
    ]);

    $this->menus[2] = Menu::create([
      'id' => 'test_without',
      'label' => 'Test Menu without fields',
    ]);

    $this->menus[1]->save();
    $this->menus[2]->save();

    FieldStorageConfig::create([
      'field_name' => 'bingo',
      'type' => 'string',
      'entity_type' => 'menu_link_content',
    ])->save();

    FieldConfig::create([
      'field_name' => 'bingo',
      'entity_type' => 'menu_link_content',
      'bundle' => $this->menus[1]->id(),
      'label' => 'Bingo',
      'required' => FALSE,
    ])->save();

    $this->links[1] = MenuLinkContent::create([
      'title' => 'Test link 1',
      'link' => ['uri' => 'internal:/node/' . $this->nodes[1]->id()],
      'menu_name' => $this->menus[1]->id(),
      'bingo' => 'Bingo!',
      'weight' => 1,
    ]);

    $this->links[2] = MenuLinkContent::create([
      'title' => 'Test link 2',
      'link' => ['uri' => 'internal:/node/' . $this->nodes[2]->id()],
      'menu_name' => $this->menus[1]->id(),
      'weight' => 2,
    ]);

    $this->links[3] = MenuLinkContent::create([
      'title' => 'Test external',
      'link' => ['uri' => 'https://www.google.com'],
      'menu_name' => $this->menus[1]->id(),
      'weight' => 3,
    ]);

    $this->links[4] = MenuLinkContent::create([
      'title' => 'Test link on without menu',
      'link' => ['uri' => 'internal:/node/' . $this->nodes[1]->id()],
      'menu_name' => $this->menus[2]->id(),
      'weight' => 1,
    ]);

    foreach ($this->links as $link) {
      $link->save();
    }

    $this->setEntityConfig('menu', 'test_with', [
      'enabled' => TRUE,
    ]);

    $this->setEntityConfig('menu', 'test_without', [
      'enabled' => TRUE,
    ]);

    $this->setFieldConfig('menu_link_content', 'test_with', 'bingo', [
      'enabled' => TRUE,
    ]);
  }

  /**
   * Test menu loads a menu with a field.
   */
  public function testMenuLoadWithField(): void {
    $query = <<<GQL
      query {
        menu(name: TEST_WITH) {
          id
          name
          items {
            title
            extras {
              ... on MenuLinkContentInterface {
                id
              }
              ... on MenuLinkContentTestWith {
                bingo
              }
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $menu = $content['data']['menu'];

    $this->assertEquals($this->menus[1]->uuid(), $menu['id']);
    $this->assertEquals($this->menus[1]->label(), $menu['name']);

    $this->assertCount(3, $menu['items']);

    $this->assertEquals('Test link 1', $menu['items'][0]['title']);
    $this->assertEquals('Test link 2', $menu['items'][1]['title']);

    $this->assertEquals('Bingo!', $menu['items'][0]['extras']['bingo']);
    $this->assertNull($menu['items'][1]['extras']['bingo']);
  }

  /**
   * Test menu loads a menu with a field.
   */
  public function testMenuLoadWithoutField(): void {
    $query = <<<GQL
      query {
        menu(name: TEST_WITHOUT) {
          id
          name
          items {
            title
            extras {
              ... on MenuLinkContentInterface {
                id
              }
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $menu = $content['data']['menu'];

    $this->assertEquals($this->menus[2]->uuid(), $menu['id']);
    $this->assertEquals($this->menus[2]->label(), $menu['name']);

    $this->assertCount(1, $menu['items']);

    $this->assertEquals('Test link on without menu', $menu['items'][0]['title']);

    $this->assertArrayNotHasKey('bingo', $menu['items'][0]['extras']);
  }

}
