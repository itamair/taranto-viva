<?php

/**
 * @file
 * Hooks provided by GraphQL Compose Routes module.
 */

use Drupal\graphql\GraphQL\Execution\FieldContext;

/**
 * Alter the incoming URL path for resolution to Url.
 *
 * Potential uses could be prefixed paths or adhoc corrections.
 *
 * @param string|null $path
 *   The incoming path.
 * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
 *   The GraphQL producer field context.
 */
function hook_graphql_compose_routes_incoming_alter(&$path, FieldContext $context): void {
  if (preg_match('/^\/color/i', $path ?: '')) {
    $path = '/colour';
  }
}

/**
 * Route union type resolver alter.
 *
 * Add the ability to resolve your custom route types
 * within the RouteUnion type.
 *
 * @param mixed $value
 *   The value being resolved.
 * @param string|null $type
 *   The type to resolve to.
 */
function hook_graphql_compose_routes_union_alter($value, ?string &$type): void {
  if ($value instanceof \Drupal\Core\Url) {
    $type = 'Whatever';
  }
}
