<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_edges;

use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql_compose_edges\Wrappers\Cursor;
use Drupal\graphql_compose_edges\Wrappers\EdgeInterface;
use GraphQL\Error\UserError;
use GraphQL\Executor\Promise\Adapter\SyncPromise;

/**
 * Provides a new paginated entity query.
 */
class EntityConnection implements ConnectionInterface {

  /**
   * The number of nodes a client is allowed to fetch on this connection.
   */
  public const MAX_LIMIT = 100;

  /**
   * Fetch the first N results.
   *
   * @var int|null
   */
  protected ?int $first = NULL;

  /**
   * Fetch the last N results.
   *
   * @var int|null
   */
  protected ?int $last = NULL;

  /**
   * The cursor that results were fetched after.
   *
   * @var string|null
   */
  protected ?string $after = NULL;

  /**
   * The cursor that results were fetched before.
   *
   * @var string|null
   */
  protected ?string $before = NULL;

  /**
   * Whether the sorting is requested in reversed order.
   *
   * @var bool
   */
  protected bool $reverse = FALSE;

  /**
   * The filters to apply to the cursor.
   *
   * @var array
   */
  protected array $filters = [];

  /**
   * The cache context for this request.
   *
   * @var \Drupal\graphql\GraphQL\Execution\FieldContext
   */
  protected FieldContext $context;

  /**
   * The result-set of this connection.
   *
   * @var \GraphQL\Executor\Promise\Adapter\SyncPromise|null
   */
  protected ?SyncPromise $result;

  /**
   * Create a new PaginatedEntityQuery.
   *
   * @param \Drupal\graphql_compose_edges\ConnectionQueryHelperInterface $queryHelper
   *   The query helper that knows how to fetch the data for this connection.
   */
  public function __construct(
    protected ConnectionQueryHelperInterface $queryHelper,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function setCacheContext(FieldContext $context): static {
    $this->context =& $context;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContext(): FieldContext {
    return $this->context;
  }

  /**
   * {@inheritdoc}
   */
  public function setFilter(string $key, $value, string $filter_class): static {
    $this->filters[$key] = [
      'value' => $value,
      'class' => $filter_class,
    ];

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilter($key) {
    return $this->filters[$key]['value'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilters(): array {
    return $this->filters;
  }

  /**
   * Get the data result for this connection.
   *
   * @return \GraphQL\Executor\Promise\Adapter\SyncPromise
   *   The result for this connection's query.
   */
  protected function getResult(): SyncPromise {
    return $this->result ??= $this->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryHelper(): ConnectionQueryHelperInterface {
    return $this->queryHelper;
  }

  /**
   * {@inheritdoc}
   */
  public function edges(): SyncPromise {
    return $this->getResult()->then($this->sliceAndReorder(...));
  }

  /**
   * {@inheritdoc}
   */
  public function nodes(): SyncPromise {
    return $this->edges()->then(function ($edges) {

        // Nodes are a shortcut into the edge.
        // We only need the nodes.
        return array_map(
          fn (EdgeInterface $edge) => $edge->getNode(),
          $edges
        );
    });
  }

  /**
   * Apply slicing and reordering to the result so that it can be transmitted.
   *
   * The result from the database may be out of order and have over-fetched.
   * When returning edges or nodes, this needs to be compensated in the
   * same way. This function removes the over-fetching and ensures the
   * results are in the requested order.
   *
   * @param \Drupal\graphql_compose_edges\Wrappers\EdgeInterface[] $edges
   *   Edges to check access on.
   *
   * @return \Drupal\graphql_compose_edges\Wrappers\EdgeInterface[]
   *   Filtered edge nodes.
   */
  protected function sliceAndReorder(array $edges): array {
    // To allow for pagination we over-fetch results by one above
    // the limits so we must fix that now.
    $edges = array_slice($edges, 0, $this->first ?: $this->last);

    if ($this->shouldReverseResultEdges()) {
      $edges = array_reverse($edges);
    }

    return $edges;
  }

  /**
   * Execute the query to fetch the entities in this connection.
   *
   * @return \GraphQL\Executor\Promise\Adapter\SyncPromise
   *   A promise that resolves to the edges of this connection.
   *
   * @throws \GraphQL\Error\UserError
   *   Invalid cursor provided.
   */
  protected function execute(): SyncPromise {
    $query = $this->queryHelper->getQuery();
    $sort_field = $this->queryHelper->getSortField();
    $id_field = $this->queryHelper->getIdField();

    // Because MySQL only allows us to provide positive range limits (we can't
    // select backwards) we must change the query order based on the meaning of
    // first and last. This in turn is dependant on whether we're selecting in
    // ascending (non-reversed) or descending (reversed) order.
    // The order is ascending if
    // - we want the first results in a non reversed query
    // - we want the last results in a reversed query
    // The order is descending if
    // - we want the first results in a reversed query
    // - we want the last results in a non reversed query.
    $field_query_order = (!is_null($this->first) && !$this->reverse) || (!is_null($this->last) && $this->reverse)
      ? 'ASC'
      : 'DESC';

    // @todo flesh out field sorting.
    $id_query_order = (!is_null($this->first) && !$this->reverse) || (!is_null($this->last) && $this->reverse)
      ? 'ASC'
      : 'DESC';

    // If a cursor is provided then we alter the condition to select the
    // elements on the correct side of the cursor.
    $cursor = $this->after ?: $this->before;

    if (!is_null($cursor)) {
      $cursor_object = Cursor::fromCursorString($cursor);

      // Validate the cursor against the query expected.
      $cursor_valid = $cursor_object->validate(
        $this->queryHelper->getEntityTypeId(),
        $this->queryHelper->getSortKey(),
        $this->filters
      );

      if (!$cursor_valid) {
        throw new UserError(sprintf('Invalid cursor %s', $cursor));
      }

      $operator = (!is_null($this->before) && !$this->reverse) || (!is_null($this->after) && $this->reverse) ? '<' : '>';

      $cursor_id = $cursor_object->getBackingId();
      $cursor_sort = $cursor_object->getSortValue();

      $pagination_condition = $query->orConditionGroup();
      $pagination_condition->condition($sort_field, $cursor_sort, $operator);

      // If the sort field is different than the ID then it's not guaranteed to
      // be unique. However, above we exclude values that are the same as those
      // of the cursor. We want to include those but use the ID to make sure
      // they're on the correct side of the cursor.
      if ($sort_field !== $id_field) {
        $pagination_condition->condition(
          $query->andConditionGroup()
            ->condition($sort_field, $cursor_sort, '=')
            ->condition($id_field, $cursor_id, $operator)
        );
      }

      $query->condition($pagination_condition);
    }

    // From assertValidPagination we know that we either have a first or a last.
    $limit = $this->first ?: $this->last;

    // Fetch N + 1 so we know if there are more pages.
    $query->range(0, $limit + 1);

    // Sort by field and ID to ensure a stable sort.
    $query->sort($sort_field, $field_query_order);
    if ($sort_field !== $id_field) {
      $query->sort($id_field, $id_query_order);
    }

    // Add conditional filters.
    foreach ($this->filters as $filter) {
      $filter_instance = new $filter['class']($this, $cursor_object ?? NULL);
      $filter_instance->apply($query);
    }

    // Fetch the result for the query.
    $result = $query->execute();

    return $this->queryHelper->resolve($this, $result);
  }

  /**
   * Whether the edges from our result should be reversed.
   *
   * To compensate for the ordering needed for the range selector we must
   * sometimes flip the result. The first 3 results of a non-reverse query
   * are the same as the last 3 results of a reversed query but they are in
   * reverse order.
   * The results must be flipped if
   * - we want the last results in a reversed query
   * - we want the last results in a non reversed query.
   *
   * @return bool
   *   Whether the edges returned from `getResult()` as in reverse order.
   */
  protected function shouldReverseResultEdges(): bool {
    return !is_null($this->last);
  }

  /**
   * {@inheritdoc}
   */
  public function pageInfo(): SyncPromise {
    return $this->getResult()->then(function ($edges) {
      /** @var \Drupal\graphql_compose_edges\Wrappers\Edge[] $edges */

      // If we don't have any results then we won't have any other pages either.
      if (empty($edges)) {
        return [
          'hasNextPage' => FALSE,
          'hasPreviousPage' => FALSE,
          'startCursor' => NULL,
          'endCursor' => NULL,
        ];
      }

      // Count the number of elements that we have so we can check if we have
      // future pages.
      $count = count($edges);

      // The last item is either based on the limit or on the number of fetched
      // items if it's below the limit. Correct for 0 based indexing.
      $last_index = min($this->first ?: $this->last, $count) - 1;

      return [
        // We have a next page if the before cursor was provided (we assume
        // calling code has validated the cursor) or if N first results were
        // requested and we have more.
        'hasNextPage' => $this->before !== NULL || ($this->first !== NULL && $this->first < $count),

        // We have a previous page if the after cursor was provided (we assume
        // calling code has validated the cursor) or if N last results were
        // requested and we have more.
        'hasPreviousPage' => $this->after !== NULL || ($this->last !== NULL && $this->last < $count),

        // The start cursor is always the first cursor in the result-set..
        'startCursor' => $this->shouldReverseResultEdges() ? $edges[$last_index]->getCursor() : $edges[0]->getCursor(),

        // The end cursor is always the last cursor in the result-set..
        'endCursor' => $this->shouldReverseResultEdges() ? $edges[0]->getCursor() : $edges[$last_index]->getCursor(),
      ];
    });
  }

  /**
   * {@inheritdoc}
   *
   * @throws \RuntimeException
   */
  public function setPagination(?int $first, ?string $after, ?int $last, ?string $before, ?bool $reverse): static {
    // Disallow changing pagination after a query has been performed
    // because the way we treat the results depends on it.
    if (isset($this->result)) {
      throw new \RuntimeException('Cannot change pagination after a query for a connection has been executed.');
    }

    $this->validatePagination($first, $after, $last, $before);

    $this->first = $first;
    $this->after = $after;
    $this->last = $last;
    $this->before = $before;
    $this->reverse = (bool) $reverse;

    return $this;
  }

  /**
   * Ensures the user entered limits (first/last) are valid.
   *
   * @param int|null $first
   *   Request to retrieve first n results.
   * @param string|null $after
   *   The cursor after which to fetch results.
   * @param int|null $last
   *   Request to retrieve last n results.
   * @param string|null $before
   *   The cursor before which to fetch results.
   *
   * @throws \GraphQL\Error\UserError
   *   Error thrown when a user has specified invalid arguments.
   */
  protected function validatePagination(?int $first, ?string $after, ?int $last, ?string $before): void {

    // The limit on the amount of results that may be requested.
    $limit = $this->queryHelper->getLimit() ?: self::MAX_LIMIT;

    if ($first > $limit) {
      throw new UserError(sprintf('First may not be larger than %s.', $limit));
    }
    if ($last > $limit) {
      throw new UserError(sprintf('Last may not be larger than %s.', $limit));
    }

    // The below if-statements are derived to be able to implement the Relay
    // connection spec in a sane way. They ensure we only ever need to care
    // about either (first and after) or (last and before) and no other
    // combinations.
    if (is_null($first) && is_null($last)) {
      throw new UserError('You must provide one of first or last.');
    }
    if (!is_null($first) && !is_null($last)) {
      throw new UserError('Providing both first and last is not supported.');
    }
    if (!is_null($first) && !is_null($before)) {
      throw new UserError('Using first with before is not supported.');
    }
    if (!is_null($last) && !is_null($after)) {
      throw new UserError('Using last with after is not supported.');
    }
    if ($first <= 0 && !is_null($first)) {
      throw new UserError('First must be a positive integer when provided.');
    }
    if ($last <= 0 && !is_null($last)) {
      throw new UserError('Last must be a positive integer when provided.');
    }
  }

}
