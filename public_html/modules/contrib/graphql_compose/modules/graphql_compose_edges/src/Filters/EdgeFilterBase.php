<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_edges\Filters;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\graphql_compose_edges\ConnectionInterface;
use Drupal\graphql_compose_edges\ConnectionQueryHelperInterface;
use Drupal\graphql_compose_edges\Wrappers\Cursor;

/**
 * Base class for edge filters.
 */
abstract class EdgeFilterBase implements EdgeFilterInterface {

  /**
   * The user to check access against.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The Drupal config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Filter a connection edge.
   *
   * @param \Drupal\graphql_compose_edges\ConnectionInterface $connection
   *   The connection to filter.
   * @param \Drupal\graphql_compose_edges\Wrappers\Cursor|null $cursor
   *   Any cursor incoming to the query.
   */
  public function __construct(
    protected ConnectionInterface $connection,
    protected ?Cursor $cursor,
  ) {}

  /**
   * The user to check access against.
   *
   * @return \Drupal\Core\Session\AccountProxyInterface
   *   The user to check access against.
   */
  protected function currentUser(): AccountProxyInterface {
    return $this->currentUser ??= \Drupal::currentUser();
  }

  /**
   * Get the Drupal config factory.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface
   *   The Drupal config factory.
   */
  protected function configFactory(): ConfigFactoryInterface {
    return $this->configFactory ??= \Drupal::configFactory();
  }

  /**
   * Get the connection query helper.
   *
   * @return \Drupal\graphql_compose_edges\ConnectionQueryHelperInterface
   *   The connection query helper.
   */
  protected function getQueryHelper(): ConnectionQueryHelperInterface {
    return $this->connection->getQueryHelper();
  }

  /**
   * Prefer to get the cursor value first.
   *
   * @param string $key
   *   The key for the filter.
   *
   * @return mixed
   *   The filter value.
   */
  protected function getFilter($key) {
    return $this->cursor?->getFilter($key) ?: $this->connection->getFilter($key);
  }

}
