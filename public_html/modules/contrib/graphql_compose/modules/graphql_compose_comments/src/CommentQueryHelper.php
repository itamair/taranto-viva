<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_comments;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\comment\CommentFieldItemList;
use Drupal\graphql_compose_edges\EntityConnectionQueryHelper;

/**
 * Load comments for entity.
 */
class CommentQueryHelper extends EntityConnectionQueryHelper {

  /**
   * Sort by created time always.
   *
   * @var string|null
   */
  protected ?string $sortKey = 'CREATED_AT';

  /**
   * The entity type.
   *
   * @var string
   */
  protected string $entityTypeId = 'comment';

  /**
   * Entity bundle (set per field attachment).
   *
   * @var string
   */
  protected string $entityBundleId;

  /**
   * Create a new connection query helper.
   *
   * @param \Drupal\comment\CommentFieldItemList $fieldList
   *   The field this comment is attached to.
   */
  public function __construct(
    protected CommentFieldItemList $fieldList,
  ) {
    $this->entityBundleId = $fieldList
      ->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->getSetting('comment_type');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuery(): QueryInterface {

    // Standard query object.
    $query = parent::getQuery();

    $field_entity = $this->fieldList->getEntity();
    $field_definition = $this->fieldList->getFieldDefinition();

    // Limit to the parent entity.
    $query->condition('entity_type', $field_entity->getEntityTypeId());
    $query->condition('entity_id', $field_entity->id());
    $query->condition('field_name', $field_definition->getName());

    return $query;
  }

}
