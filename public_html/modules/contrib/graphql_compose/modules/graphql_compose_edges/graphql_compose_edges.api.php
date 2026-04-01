<?php

/**
 * @file
 * Hooks provided by GraphQL Compose Edges module.
 */

use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\DataProducerPluginInterface;
use Drupal\graphql_compose_edges\ConnectionInterface;

/**
 * Modify the edge connection for entities.
 *
 * @param \Drupal\graphql\Plugin\DataProducerPluginInterface $producer
 *   The data producer plugin.
 * @param \Drupal\graphql_compose_edges\ConnectionInterface $connection
 *   The connection to modify.
 * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
 *   The cache context.
 */
function hook_graphql_compose_edges_alter(DataProducerPluginInterface $producer, ConnectionInterface $connection, FieldContext $context): void {
  [$entity_type, $bundle] = explode(':', $producer->getDerivativeId());

  // Example adding a custom filter against the node:article bundle.
  if ($entity_type === 'node' && $bundle === 'article') {
    $connection->setFilter('custom', TRUE, CustomFilter::class);
    $context->addCacheTags(['my_thing']);
  }
}
