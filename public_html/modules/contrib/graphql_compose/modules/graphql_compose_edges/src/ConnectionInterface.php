<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_edges;

use Drupal\graphql\GraphQL\Execution\FieldContext;
use GraphQL\Executor\Promise\Adapter\SyncPromise;

/**
 * Provides the interface for connections.
 */
interface ConnectionInterface {

  /**
   * Specifies the pagination parameters for this query.
   *
   * This can only be called before results have been fetched.
   *
   * @param int|null $first
   *   The limit of N first results (either first XOR last must be set).
   * @param string|null $after
   *   The cursor after which to fetch results (when using `$first`).
   * @param int|null $last
   *   The limit of N last results (either first XOR last must be set).
   * @param string|null $before
   *   The cursor before which to fetch results (when using `$last`).
   * @param bool|null $reverse
   *   Whether the sorting is in reversed order.
   *
   * @return static
   *   This connection instance.
   */
  public function setPagination(?int $first, ?string $after, ?int $last, ?string $before, ?bool $reverse): static;

  /**
   * Set the cache context for this request.
   *
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   The cache context for this request.
   *
   * @return static
   *   The connection instance.
   */
  public function setCacheContext(FieldContext $context): static;

  /**
   * Get the cache context for this request.
   *
   * @return \Drupal\graphql\GraphQL\Execution\FieldContext
   *   The cache context for this request.
   */
  public function getCacheContext(): FieldContext;

  /**
   * Get the page info from the connection.
   *
   * @return \GraphQL\Executor\Promise\Adapter\SyncPromise
   *   An promise that resolves to an array containing the fields of page info.
   */
  public function pageInfo(): SyncPromise;

  /**
   * Get the edges from the connection.
   *
   * @return \GraphQL\Executor\Promise\Adapter\SyncPromise
   *   A promise that resolves to an array of EntityEdge instances.
   */
  public function edges(): SyncPromise;

  /**
   * Get hte nodes for this connection.
   *
   * This allows bypassing of the edges in case edge information isn't needed.
   *
   * @return \GraphQL\Executor\Promise\Adapter\SyncPromise
   *   A promise that resolves to an array of entities.
   */
  public function nodes(): SyncPromise;

  /**
   * Set a filter on the cursor.
   *
   * @param string $key
   *   The filter key.
   * @param mixed $value
   *   The filter value.
   * @param string $filter_class
   *   The class name of the callback to use to filter the query.
   *
   * @return static
   *   The cursor instance.
   */
  public function setFilter(string $key, $value, string $filter_class): static;

  /**
   * Get a filter value from the cursor.
   *
   * @return mixed|null
   *   The filter value or NULL if the filter is not set.
   */
  public function getFilter($key);

  /**
   * Get all filters on connection.
   *
   * @return array
   *   The filters for this connection.
   */
  public function getFilters(): array;

  /**
   * Get the query helper for this connection.
   *
   * @return \Drupal\graphql_compose_edges\ConnectionQueryHelperInterface
   *   The query helper for this connection.
   */
  public function getQueryHelper(): ConnectionQueryHelperInterface;

}
