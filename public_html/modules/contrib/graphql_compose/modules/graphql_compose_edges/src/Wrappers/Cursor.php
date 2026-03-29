<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_edges\Wrappers;

use Drupal\Component\Serialization\Json;

/**
 * GraphQL cursor for pagination.
 *
 * The cursor is used to find the current position in the connection result
 * set.
 *
 * A critical feature of the cursor is that you can continue to paginate even
 * if the node that you grabbed the cursor from ceases to exist (or is
 * modified), so effectively it details the "value" it's sorted by.
 */
class Cursor {

  /**
   * Create a new GraphQL cursor.
   *
   * A cursor should provide a stable point in pagination results, even if the
   * type that backs it is removed or altered.
   *
   * @param string $backingType
   *   The internal type of the object the cursor is for.
   * @param int $backingId
   *   The internal ID of the object the cursor is for. Must be unique for the
   *   backingType as it may be used for stable sorting when there are
   *   duplicates in the value for the sort field.
   * @param string|null $sortKey
   *   The key to sort by. How this maps to object values is determined by the
   *   connection responsible for the edge.
   * @param mixed $sortValue
   *   The value to sort by.
   * @param array $filters
   *   An array of filters to apply to the cursor.
   */
  public function __construct(
    protected string $backingType,
    protected int $backingId,
    protected ?string $sortKey,
    protected mixed $sortValue,
    protected array $filters = [],
  ) {}

  /**
   * Magic method to stringify this object.
   *
   * @return string
   *   The string that can be returned in GraphQL responses.
   *
   * @see toCursorString()
   */
  public function __toString(): string {
    return $this->toCursorString();
  }

  /**
   * Convert the Cursor to a string.
   *
   * Classes overwriting this method should also overwrite fromCursorString
   * since the transformation between cursor class and string is considered to
   * be an implementation detail.
   *
   * @return string
   *   The string that can be returned in GraphQL responses.
   */
  public function toCursorString(): string {
    $cursor = [
      'backingType' => $this->backingType,
      'backingId' => $this->backingId,
      'sortKey' => $this->sortKey,
      'sortValue' => $this->sortValue,
      'filters' => $this->filters,
    ];

    $cursor = array_filter($cursor, fn ($value) => !is_null($value));

    return base64_encode(Json::encode($cursor));
  }

  /**
   * Hydrate a cursor into a queryable object.
   *
   * Classes overwriting this method should also overwrite toCursorString
   * since the transformation between cursor class and string is considered to
   * be an implementation detail.
   *
   * @param string $cursor
   *   The cursor string as returned by self::toCursorString().
   *
   * @return static|null
   *   An instance of the cursor class or null in case of an invalid cursor.
   */
  public static function fromCursorString(string $cursor): ?static {
    $encoded_object = base64_decode($cursor, TRUE);
    if ($encoded_object === FALSE) {
      return NULL;
    }

    $args = Json::decode($encoded_object);

    return new static(
      $args['backingType'],
      (int) $args['backingId'],
      $args['sortKey'] ?? NULL,
      $args['sortValue'] ?? NULL,
      $args['filters'] ?? [],
    );
  }

  /**
   * Get the backing ID for this cursor.
   *
   * @return int
   *   The internal identifier for the object that created this cursor.
   */
  public function getBackingId(): int {
    return $this->backingId;
  }

  /**
   * Get the sort value for this cursor.
   *
   * @return mixed
   *   The sort value for this cursor.
   */
  public function getSortValue() {
    return $this->sortValue;
  }

  /**
   * Set a filter on the cursor.
   *
   * @return static
   *   The cursor instance.
   */
  public function setFilter($key, $value) {
    $this->filters[$key] = $value;

    return $this;
  }

  /**
   * Get a filter from the cursor.
   *
   * @return mixed|null
   *   The filter value or NULL if the filter is not set.
   */
  public function getFilter($key) {
    return $this->filters[$key] ?? NULL;
  }

  /**
   * Whether the cursor is valid for the specified key and type.
   *
   * @param string|null $backing_type
   *   The connection entity type.
   * @param string|null $sort_key
   *   The sort key that this cursor should be for.
   * @param array $filters
   *   If provided will require the filter keys to match.
   *
   * @return bool
   *   Whether this cursor is valid for the provided arguments.
   */
  public function validate(?string $backing_type, ?string $sort_key, array $filters): bool {

    $key_match = $this->sortKey === $sort_key;
    $backing_match = $this->backingType === $backing_type;

    $filters_match = array_diff_key($this->filters, $filters) ? FALSE : TRUE;

    return $key_match && $backing_match && $filters_match;
  }

}
