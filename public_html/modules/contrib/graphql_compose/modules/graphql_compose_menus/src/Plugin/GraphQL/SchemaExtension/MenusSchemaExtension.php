<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_menus\Plugin\GraphQL\SchemaExtension;

use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Url;
use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistryInterface;
use Drupal\graphql_compose\Plugin\GraphQL\SchemaExtension\ResolverOnlySchemaExtensionPluginBase;
use Drupal\menu_link_content\Plugin\Menu\MenuLinkContent;
use Symfony\Component\DependencyInjection\ContainerInterface;

use function Symfony\Component\String\u;

/**
 * Add menus to the Schema.
 *
 * @SchemaExtension(
 *   id = "graphql_compose_menus",
 *   name = "GraphQL Compose Menus",
 *   description = @Translation("Add menus to the Schema."),
 *   schema = "graphql_compose",
 * )
 */
class MenusSchemaExtension extends ResolverOnlySchemaExtensionPluginBase {

  /**
   * Link attributes plugin manager.
   *
   * @var \Drupal\link_attributes\LinkAttributesManager|null
   */
  protected $linkAttributesManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition
    );

    $instance->linkAttributesManager = $container->get(
      'plugin.manager.link_attributes',
      ContainerInterface::NULL_ON_INVALID_REFERENCE
    );

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function registerResolvers(ResolverRegistryInterface $registry): void {
    $builder = new ResolverBuilder();

    $registry->addFieldResolver(
      'Query',
      'menu',
      $builder->compose(
        $builder->produce('language_context')
          ->map('language', $builder->fromArgument('langcode')),

        $builder->produce('entity_load')
          ->map('type', $builder->fromValue('menu'))
          ->map('id', $builder->produce('schema_enum_value')
            ->map('type', $builder->fromValue('MenuAvailable'))
            ->map('value', $builder->fromArgument('name')),
        ),

        $builder->context('menu', $builder->fromParent()),
      )
    );

    // Menu name.
    $registry->addFieldResolver('Menu', 'name',
      $builder->produce('entity_label')
        ->map('entity', $builder->fromParent())
      );

    // Menu items.
    $registry->addFieldResolver('Menu', 'items',
      $builder->produce('menu_links')
        ->map('menu', $builder->fromParent())
      );

    // Menu link UUID.
    $registry->addFieldResolver('MenuItem', 'id',
      $builder->compose(
        $builder->produce('menu_tree_link')->map('element', $builder->fromParent()),
        $builder->callback(fn(MenuLinkInterface $link) => $link->getDerivativeId() ?: $link->getPluginId()),
      )
    );

    // Menu title.
    $registry->addFieldResolver('MenuItem', 'title',
      $builder->produce('menu_link_label')
        ->map('link', $builder->produce('menu_tree_link')
          ->map('element', $builder->fromParent())),
      );

    // Menu description.
    $registry->addFieldResolver('MenuItem', 'description',
      $builder->produce('menu_link_description')
        ->map('link', $builder->produce('menu_tree_link')
          ->map('element', $builder->fromParent())),
    );

    // Menu url.
    $registry->addFieldResolver('MenuItem', 'url',
      $builder->compose(
        $builder->produce('menu_tree_link')
          ->map('element', $builder->fromParent()),

        $builder->cond([
          [
            // Condition: Does the translatable_menu_link_uri module exist?
            $builder->callback(function (MenuLinkInterface $link) {
              $module_exists = $this->moduleHandler->moduleExists('translatable_menu_link_uri');
              return $module_exists && $link instanceof MenuLinkContent;
            }),

            $builder->produce('menu_link_url_override')
              ->map('entity', $builder->produce('menu_link_entity')
                ->map('link', $builder->fromParent())),
          ], [
            // Condition: Default. Url as normal.
            $builder->fromValue(TRUE),

            $builder->produce('menu_link_url')
              ->map('link', $builder->fromParent()),
          ],
        ]),

        $builder->produce('url_path')
          ->map('url', $builder->fromParent()),
      )
    );

    // Menu link language.
    $registry->addFieldResolver('MenuItem', 'langcode',
      $builder->compose(
        $builder->produce('menu_tree_link')
          ->map('element', $builder->fromParent()),

        $builder->cond([
          [
            // Condition: Is the link a MenuLinkContent entity?
            $builder->callback(function (MenuLinkInterface $link) {
              return $link instanceof MenuLinkContent;
            }),

            $builder->produce('entity_language')
              ->map('entity', $builder->produce('menu_link_entity')
                ->map('link', $builder->fromParent())),
          ], [
            // Condition: Default. The parent menu language.
            $builder->fromValue(TRUE),
            $builder->produce('entity_language')
              ->map('entity', $builder->fromContext('menu')),
          ],
        ]),
      )
    );

    // Menu internal.
    $registry->addFieldResolver('MenuItem', 'internal',
      $builder->compose(
        $builder->produce('menu_link_url')
          ->map('link', $builder->produce('menu_tree_link')
            ->map('element', $builder->fromParent())),

        $builder->callback(fn(Url $url) => $url->isRouted()),
      )
    );

    // Menu expanded.
    $registry->addFieldResolver('MenuItem', 'expanded',
      $builder->produce('menu_link_expanded')
        ->map('link', $builder->produce('menu_tree_link')
          ->map('element', $builder->fromParent())),
    );

    // Menu children.
    $registry->addFieldResolver('MenuItem', 'children',
      $builder->produce('menu_tree_subtree')
        ->map('element', $builder->fromParent())
    );

    // Menu attributes.
    $registry->addFieldResolver('MenuItem', 'attributes',
      $builder->produce('menu_tree_link')
        ->map('element', $builder->fromParent()),
    );

    $attributes = ['class'];

    if ($this->moduleHandler->moduleExists('menu_link_attributes')) {
      $attributes = array_merge(
        $attributes,
        array_keys($this->configFactory->get('menu_link_attributes.config')->get('attributes') ?: [])
      );
    }
    elseif ($this->moduleHandler->moduleExists('link_attributes_menu_link_content')) {
      $attributes = array_merge(
        $attributes,
        array_keys($this->linkAttributesManager->getDefinitions()),
      );
    }

    foreach ($attributes as $attr) {
      $registry->addFieldResolver(
        'MenuItemAttributes',
        u($attr)->camel()->toString(),
        $builder->produce('menu_link_attribute')
          ->map('link', $builder->fromParent())
          ->map('attribute', $builder->fromValue($attr))
      );
    }

    // Menu link extras.
    if ($this->moduleHandler->moduleExists('menu_item_extras')) {
      $registry->addFieldResolver('MenuItem', 'extras',
        $builder->produce('menu_link_entity')
          ->map('link', $builder->produce('menu_tree_link')
            ->map('element', $builder->fromParent())),
      );
    }

    // Menu route.
    // This is toggled by users.
    $registry->addFieldResolver('MenuItem', 'route',
      $builder->compose(
        $builder->produce('menu_tree_link')
          ->map('element', $builder->fromParent()),

        $builder->cond([
          [
            $builder->callback(function (MenuLinkInterface $link) {
              return static::menuRouteEnabled($link->getMenuName());
            }),
            $builder->fromParent(),
          ], [
            $builder->fromValue(TRUE),
            $builder->fromValue(NULL),
          ],
        ]),

        $builder->produce('menu_link_url')
          ->map('link', $builder->fromParent()),
      )
    );
  }

  /**
   * Check wether a user has enabled route resolution on a menu.
   *
   * @param string $name
   *   The menu name.
   *
   * @return bool
   *   Whether the menu route is enabled.
   */
  public static function menuRouteEnabled(string $name): bool {
    static $enabled;

    if (!isset($enabled)) {
      $settings = \Drupal::config('graphql_compose.settings');
      $enabled = $settings->get('entity_config.menu') ?: [];
    }

    return (bool) ($enabled[$name]['menu_route_enabled'] ?? FALSE);
  }

}
