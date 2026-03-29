<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose\Functional\Contrib;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\link\LinkItemInterface;
use Drupal\Tests\graphql_compose\Functional\GraphQLComposeBrowserTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\node\NodeInterface;
use Drupal\system\Entity\Menu;
use Drupal\system\MenuInterface;

/**
 * Tests specific to GraphQL Compose menus with menu link attributes.
 *
 * @group legacy
 */
class LinkAttributesTest extends GraphQLComposeBrowserTestBase {

  /**
   * We aren't concerned with strict config schema for contrib.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE; // @phpcs:ignore

  /**
   * The test menu.
   *
   * @var \Drupal\system\MenuInterface
   */
  protected MenuInterface $menu;

  /**
   * The test node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $node;

  /**
   * The test menu link.
   *
   * @var \Drupal\menu_link_content\Entity\MenuLinkContentInterface
   */
  protected MenuLinkContentInterface $link;

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
      'id' => 'test',
      'label' => 'Test Menu with fields',
    ]);

    $this->menu->save();

    $this->link = MenuLinkContent::create([
      'title' => 'Test',
      'menu_name' => $this->menu->id(),
      'link' => [
        'uri' => 'http://example.com',
        'options' => [
          'attributes' => [
            'class' => ['a', 'b', 'c'],
            'rel' => ['nofollow'],
          ],
        ],
      ],
    ]);

    $this->link->save();

    $this->createContentType([
      'type' => 'test',
      'name' => 'Test node type',
    ]);

    FieldStorageConfig::create([
      'field_name' => 'field_link',
      'entity_type' => 'node',
      'type' => 'link',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_link',
      'entity_type' => 'node',
      'bundle' => 'test',
      'label' => 'Link',
      'settings' => [
        'title' => DRUPAL_OPTIONAL,
        'link_type' => LinkItemInterface::LINK_INTERNAL,
      ],
    ])->save();

    $this->node = $this->createNode([
      'type' => 'test',
      'title' => 'Test',
      'field_link' => [
        'uri' => 'http://example.com',
        'title' => 'Test link',
        'options' => [
          'attributes' => [
            'class' => ['a', 'b', 'c'],
            'rel' => ['nofollow'],
          ],
        ],
      ],
      'status' => 1,
    ]);

    $this->node->save();

    $this->setEntityConfig('node', 'test', [
      'enabled' => TRUE,
      'query_load_enabled' => TRUE,
    ]);

    $this->setFieldConfig('node', 'test', 'field_link', [
      'enabled' => TRUE,
    ]);

    $this->setEntityConfig('menu', 'test', [
      'enabled' => TRUE,
    ]);
  }

  /**
   * Test menu link attributes.
   */
  public function testMenuLinkAttributes(): void {
    $this->container->get('module_installer')->install([
      'menu_link_attributes',
    ]);

    $query = <<<GQL
      query {
        menu(name: TEST) {
          items {
            attributes {
              class
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);
    $item = $content['data']['menu']['items'][0];

    $this->assertEquals('a b c', $item['attributes']['class']);
  }

  /**
   * Test link attributes.
   */
  public function testLinkAttributes(): void {
    $this->container->get('module_installer')->install([
      'link_attributes',
    ]);

    $query = <<<GQL
      query {
        node(id: "{$this->node->uuid()}") {
          ... on NodeTest {
            link {
              attributes {
                class
                rel
              }
            }
          }
        }

        menu(name: TEST) {
          items {
            attributes {
              class
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    // Check the link.
    $link = $content['data']['node']['link'];
    $this->assertEquals('a b c', $link['attributes']['class']);
    $this->assertEquals('nofollow', $link['attributes']['rel']);

    // Check the menu link.
    $item = $content['data']['menu']['items'][0];
    $this->assertEquals('a b c', $item['attributes']['class']);
  }

  /**
   * Test link attributes with menu sub module.
   */
  public function testLinkAttributesMenuContent(): void {
    $this->container->get('module_installer')->install([
      'link_attributes',
      'link_attributes_menu_link_content',
    ]);

    $query = <<<GQL
      query {
        menu(name: TEST) {
          items {
            attributes {
              class
              rel
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);
    $item = $content['data']['menu']['items'][0];

    $this->assertEquals('a b c', $item['attributes']['class']);
    $this->assertEquals('nofollow', $item['attributes']['rel']);
  }

  /**
   * Test a couple of modules for priority.
   */
  public function testMenuItemAttributePriority(): void {
    $this->container->get('module_installer')->install([
      'link_attributes',
      'link_attributes_menu_link_content',
      'menu_link_attributes',
    ]);

    $query = <<<GQL
      query {
        __type(name: "MenuItemAttributes") {
          name
          fields {
            name
            type {
              kind
            }
          }
        }
      }
    GQL;

    // menu_link_attributes does not have the rel attribute by default.
    $content = $this->executeQuery($query);
    $fields = $content['data']['__type']['fields'];
    $this->assertNotContains('rel', array_column($fields, 'name'));

    // Uninstall menu_link_attributes module.
    // link_attributes_menu_link_content will have the rel attribute.
    $this->container->get('module_installer')->uninstall([
      'menu_link_attributes',
    ]);

    _graphql_compose_cache_flush();

    $content = $this->executeQuery($query);
    $fields = $content['data']['__type']['fields'];
    $this->assertContains('rel', array_column($fields, 'name'));

    // Uninstall the link_attributes_menu_link_content module.
    // There should only be the class attribute left.
    $this->container->get('module_installer')->uninstall([
      'link_attributes_menu_link_content',
    ]);

    _graphql_compose_cache_flush();

    $content = $this->executeQuery($query);
    $fields = $content['data']['__type']['fields'];

    $this->assertEquals(['class'], array_column($fields, 'name'));
  }

}
