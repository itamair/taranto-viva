<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_edges\Plugin\GraphQL\SchemaExtension;

use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistryInterface;
use Drupal\graphql_compose\Plugin\GraphQL\SchemaExtension\ResolverOnlySchemaExtensionPluginBase;
use Drupal\graphql_compose_edges\EnabledBundlesTrait;

/**
 * Add route resolution.
 *
 * @SchemaExtension(
 *   id = "graphql_compose_edges",
 *   name = "GraphQL Compose Edges",
 *   description = @Translation("Multiple query loading edge connections per entity type."),
 *   schema = "graphql_compose",
 * )
 */
class EdgesSchemaExtension extends ResolverOnlySchemaExtensionPluginBase {

  use EnabledBundlesTrait;

  /**
   * {@inheritdoc}
   *
   * @throws \InvalidArgumentException
   */
  public function registerResolvers(ResolverRegistryInterface $registry): void {
    $builder = new ResolverBuilder();

    // Connection fields.
    $registry->addFieldResolver('Connection', 'edges',
      $builder->produce('connection_edges')
        ->map('connection', $builder->fromParent())
    );

    $registry->addFieldResolver('Connection', 'nodes',
      $builder->produce('connection_nodes')
        ->map('connection', $builder->fromParent())
    );

    $registry->addFieldResolver('Connection', 'pageInfo',
      $builder->produce('connection_page_info')
        ->map('connection', $builder->fromParent())
    );

    // Edge fields.
    $registry->addFieldResolver('Edge', 'cursor',
      $builder->produce('edge_cursor')
        ->map('edge', $builder->fromParent())
    );

    $registry->addFieldResolver('Edge', 'node',
      $builder->produce('edge_node')
        ->map('edge', $builder->fromParent())
    );

    // Bundle edges.
    foreach ($this->getEnabledBundlePlugins() as $bundle) {

      // Construct a deriver producer id.
      $default_producer = [
        'graphql_compose_edges_entity_type',
        $bundle->getEntityTypePlugin()->getEntityTypeId(),
        $bundle->getEntity()->id(),
      ];

      // graphql_compose_edges_entity_type:node:page.
      $default_producer = implode(':', $default_producer);

      $definition = $bundle->getEntityTypePlugin()->getPluginDefinition();

      // Some extensions may opt to put the connection elsewhere.
      // How they do that is up to that extension.
      $query_enabled = $definition['edges_query'] ?? TRUE;
      if (!$query_enabled) {
        continue;
      }

      $registry->addFieldResolver(
        'Query',
        $bundle->getNamePluralSdl(),

        $builder->compose(
          $builder->produce('language_context')
            ->map('language', $builder->fromArgument('langcode')),

          $builder->produce($definition['edges_producer'] ?? $default_producer)
            ->map('after', $builder->fromArgument('after'))
            ->map('before', $builder->fromArgument('before'))
            ->map('first', $builder->fromArgument('first'))
            ->map('last', $builder->fromArgument('last'))
            ->map('reverse', $builder->fromArgument('reverse'))
            ->map('sortKey', $builder->fromArgument('sortKey'))
            ->map('langcode', $builder->fromArgument('langcode'))
        )
      );

    }
  }

}
