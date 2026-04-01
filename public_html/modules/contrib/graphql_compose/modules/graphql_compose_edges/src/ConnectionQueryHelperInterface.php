<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_edges;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use GraphQL\Executor\Promise\Adapter\SyncPromise;

/**
 * Provides an interface for a connection query helper.
 *
 * A connection query helper provides an EntityConnection implementation with
 * the data that it needs to fetch data on the connection in a specific
 * configuration.
 */
interface ConnectionQueryHelperInterface {

  /**
   * Get the query that's at the root of this connection.
   *
   * This is a good place to apply any filtering that has been provided by the
   * client.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   An entity query or aggregate entity query.
   */
  public function getQuery(): QueryInterface;

  /**
   * Asynchronously turn the entity query result into edges.
   *
   * This can be used to process the results from the entity query and load them
   * using something like the GraphQL Entity Buffer. Transformative work should
   * be moved into the promise as much as possible.
   *
   * @param \Drupal\graphql_compose_edges\ConnectionInterface $connection
   *   The connection that's requesting the edges.
   * @param array $result
   *   The result of the entity query as started in getQuery.
   *
   * @return \GraphQL\Executor\Promise\Adapter\SyncPromise
   *   A promise that resolves into the edges for this connection.
   */
  public function resolve(ConnectionInterface $connection, array $result): SyncPromise;

  /**
   * Returns the name of the ID field of this query.
   *
   * The ID field is used as fallback in case entities have the same value for
   * the sort field. This ensures a stable sort in all cases.
   *
   * @return string
   *   The query field name to use as ID.
   */
  public function getIdField(): string;

  /**
   * Returns the entity type ID.
   *
   * @return string
   *   Entity type ID.
   */
  public function getEntityTypeId(): string;

  /**
   * Returns the sort key used for this query.
   *
   * @return string|null
   *   Sort key
   */
  public function getSortKey(): ?string;

  /**
   * Get the entity type definition.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   The entity type definition.
   */
  public function getEntityType(): EntityTypeInterface;

  /**
   * Returns the name of the field to use for sorting this connection.
   *
   * @return string
   *   The sort field name.
   */
  public function getSortField(): string;

  /**
   * Get the query limit for this connection.
   *
   * @return int|null
   *   The limit for this connection.
   */
  public function getLimit(): ?int;

}
