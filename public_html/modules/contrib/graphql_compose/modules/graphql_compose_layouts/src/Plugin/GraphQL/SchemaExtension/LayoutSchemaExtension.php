<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_layouts\Plugin\GraphQL\SchemaExtension;

use Drupal\Core\Layout\LayoutDefinition;
use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistryInterface;
use Drupal\graphql_compose\Plugin\GraphQL\SchemaExtension\ResolverOnlySchemaExtensionPluginBase;
use GraphQL\Error\UserError;

/**
 * Layout Schema Extension.
 *
 * @SchemaExtension(
 *   id = "graphql_compose_layout",
 *   name = "GraphQL Compose Layouts",
 *   description = @Translation("Layout entities"),
 *   schema = "graphql_compose",
 * )
 */
class LayoutSchemaExtension extends ResolverOnlySchemaExtensionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function registerResolvers(ResolverRegistryInterface $registry): void {
    $builder = new ResolverBuilder();

    // Real prop : SDL prop.
    $props = [
      'id' => 'id',
      'label' => 'label',
      'category' => 'category',
      'regions' => 'regions',
      'default_region' => 'defaultRegion',
    ];

    foreach ($props as $prop => $sdl) {
      $registry->addFieldResolver(
        'Layout',
        $sdl,
        $builder->compose(
          $builder->produce('layout_definition_load')
            ->map('id', $builder->fromParent()),
          $builder->produce('layout_definition_property')
            ->map('entity', $builder->fromParent())
            ->map('path', $builder->fromValue($prop))
        )
      );
    }

    $registry->addTypeResolver('Layout', function ($value) {
      if ($value instanceof LayoutDefinition) {
        return 'Layout';
      }

      throw new UserError('Could not resolve layout type.');
    });
  }

}
