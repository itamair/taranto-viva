<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_comments\Plugin\GraphQL\DataProducer;

use Drupal\comment\CommentFieldItemList;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\graphql_compose_comments\CommentQueryHelper;
use Drupal\graphql_compose_edges\EntityConnection;

/**
 * Queries entities on the platform.
 *
 * @DataProducer(
 *   id = "graphql_compose_edges_comments",
 *   name = @Translation("Query a list of entity type"),
 *   description = @Translation("Loads the entity type entities."),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("EntityConnection"),
 *   ),
 *   consumes = {
 *     "field_list" = @ContextDefinition("any",
 *       label = @Translation("Field the comment references are attached to"),
 *     ),
 *     "first" = @ContextDefinition("integer",
 *       label = @Translation("First"),
 *       required = FALSE,
 *     ),
 *     "after" = @ContextDefinition("string",
 *       label = @Translation("After"),
 *       required = FALSE,
 *     ),
 *     "last" = @ContextDefinition("integer",
 *       label = @Translation("Last"),
 *       required = FALSE,
 *     ),
 *     "before" = @ContextDefinition("string",
 *       label = @Translation("Before"),
 *       required = FALSE,
 *     ),
 *     "reverse" = @ContextDefinition("boolean",
 *       label = @Translation("Reverse"),
 *       required = FALSE,
 *     ),
 *   },
 * )
 */
class CommentEdge extends DataProducerPluginBase {

  /**
   * Resolves the request to the requested values.
   *
   * @param \Drupal\comment\CommentFieldItemList|null $field_list
   *   The field the comment references are attached to.
   * @param int|null $first
   *   Fetch the first X results.
   * @param string|null $after
   *   Cursor to fetch results after.
   * @param int|null $last
   *   Fetch the last X results.
   * @param string|null $before
   *   Cursor to fetch results before.
   * @param bool|null $reverse
   *   Reverses the order of the data.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   Cacheability context for this request.
   *
   * @return \Drupal\graphql_compose_edges\ConnectionInterface|null
   *   An entity connection with results and data about the paginated results.
   */
  public function resolve(?CommentFieldItemList $field_list, ?int $first, ?string $after, ?int $last, ?string $before, ?bool $reverse, FieldContext $context) {

    // If access was denied to the field, $field_list will be null.
    if (!$field_list) {
      return NULL;
    }

    $helper = new CommentQueryHelper($field_list);

    return (new EntityConnection($helper))
      ->setPagination($first, $after, $last, $before, $reverse)
      ->setCacheContext($context);
  }

}
