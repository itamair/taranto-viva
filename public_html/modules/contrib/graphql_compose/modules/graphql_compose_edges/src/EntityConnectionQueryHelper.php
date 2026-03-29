<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_edges;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\graphql\GraphQL\Buffers\EntityBuffer;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql_compose_edges\Wrappers\Cursor;
use Drupal\graphql_compose_edges\Wrappers\Edge;
use GraphQL\Deferred;
use GraphQL\Executor\Promise\Adapter\SyncPromise;

/**
 * Load entities of type query helper.
 */
class EntityConnectionQueryHelper implements ConnectionQueryHelperInterface {

  /**
   * The Drupal config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The Drupal entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity type definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected EntityTypeInterface $entityType;

  /**
   * The GraphQL entity buffer.
   *
   * @var \Drupal\graphql\GraphQL\Buffers\EntityBuffer
   */
  protected EntityBuffer $entityBuffer;

  /**
   * The query to use for this connection.
   *
   * @var \Drupal\Core\Entity\Query\QueryInterface
   */
  protected QueryInterface $query;

  /**
   * Create a new connection query helper.
   *
   * @param string|null $sortKey
   *   The key that is used for sorting.
   * @param string $entityTypeId
   *   The entity type to Query.
   * @param string $entityBundleId
   *   The entity bundle to Query.
   */
  public function __construct(
    protected ?string $sortKey,
    protected string $entityTypeId,
    protected string $entityBundleId,
  ) {}

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
   * Get the Drupal entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The Drupal entity type manager.
   */
  protected function entityTypeManager(): EntityTypeManagerInterface {
    return $this->entityTypeManager ??= \Drupal::entityTypeManager();
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId(): string {
    return $this->entityTypeId;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType(): EntityTypeInterface {
    return $this->entityType ??= $this->entityTypeManager()->getDefinition($this->getEntityTypeId());
  }

  /**
   * Get the GraphQL entity buffer.
   *
   * @return \Drupal\graphql\GraphQL\Buffers\EntityBuffer
   *   The GraphQL entity buffer.
   */
  protected function entityBuffer(): EntityBuffer {
    return $this->entityBuffer ??= \Drupal::service('graphql.buffer.entity');
  }

  /**
   * {@inheritdoc}
   */
  public function getSortKey(): ?string {
    return $this->sortKey;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuery(): QueryInterface {

    if (isset($this->query)) {
      return $this->query;
    }

    $entity_type = $this->getEntityType();

    $this->query = $this->entityTypeManager()
      ->getStorage($this->entityTypeId)
      ->getQuery()
      ->currentRevision()
      ->accessCheck(TRUE);

    if ($entity_type->getBundleEntityType()) {
      $this->query->condition($entity_type->getKey('bundle'), $this->entityBundleId);
    }

    return $this->query;
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(ConnectionInterface $connection, array $result): SyncPromise {
    if (empty($result)) {
      // In case of no results we create a callback the returns an empty array.
      $callback = static fn () => [];
    }
    else {
      // Otherwise we create a callback that uses the GraphQL entity buffer to
      // ensure the entities for this query are only loaded once. Even if the
      // results are used multiple times.
      $callback = $this->entityBuffer()->add(
        $this->entityTypeId,
        array_values($result)
      );
    }

    return new Deferred(function () use ($callback, $connection): array {

      $context = $connection->getCacheContext();

      // Add list cache tags and contexts.
      $context->addCacheTags($this->getEntityType()->getListCacheTags());
      $context->addCacheContexts($this->getEntityType()->getListCacheContexts());

      // Execute the buffer request.
      $entities = $callback();

      // Ensure the entities are accessible.
      $entities = $this->filterAccessible($entities, $context);

      // Get the filter values for storage on the cursors.
      $filters = array_map(
        fn($filter) => $filter['value'],
        $connection->getFilters()
      );

      // Ensure correct translations are loaded.
      if ($filters['langcode'] ?? NULL) {
        $entities = $this->getTranslated($entities, $filters['langcode']);
      }

      // Map each entity into an Edge wrapper with its own cursor.
      return array_map(function (EntityInterface $entity) use ($filters): Edge {
        $cursor = new Cursor(
          $this->entityTypeId,
          (int) $entity->id(),
          $this->sortKey,
          $this->getSortValue($entity),
          $filters
        );

        return new Edge($entity, (string) $cursor);
      }, $entities);
    });
  }

  /**
   * {@inheritdoc}
   */
  public function getIdField(): string {
    return $this->getEntityType()->getKey('id') ?: 'id';
  }

  /**
   * {@inheritdoc}
   */
  public function getLimit(): ?int {
    return $this->configFactory()
      ->get('graphql_compose.settings')
      ->get('settings.edge_max_limit');
  }

  /**
   * {@inheritdoc}
   */
  public function getSortField(): string {
    switch ($this->sortKey) {
      case 'CREATED_AT':
        return 'created';

      case 'UPDATED_AT':
        return 'changed';

      case 'TITLE':
        return $this->getEntityType()->getKey('label');

      case 'STICKY':
        return 'sticky';

      case 'PROMOTED':
        return 'promote';

      case 'WEIGHT':
        return 'weight';

      default:
        return $this->getEntityType()->getKey('id');
    }
  }

  /**
   * Get the value for an entity based on the sort key for this connection.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to sort.
   *
   * @return mixed
   *   The sort value.
   */
  protected function getSortValue(EntityInterface $entity): mixed {

    assert($entity instanceof ContentEntityInterface);

    switch ($this->sortKey) {
      case 'CREATED_AT':
        return isset($entity->created) ? (int) $entity->get('created')->value : 0;

      case 'UPDATED_AT':
        return isset($entity->changed) ? (int) $entity->get('changed')->value : 0;

      case 'TITLE':
        return $entity->label();

      case 'STICKY':
        return isset($entity->sticky) ? (int) $entity->get('sticky')->value : 0;

      case 'PROMOTED':
        return isset($entity->promote) ? (int) $entity->get('promote')->value : 0;

      case 'WEIGHT':
        return isset($entity->weight) ? (int) $entity->get('weight')->value : 0;

      default:
        return $entity->id();
    }
  }

  /**
   * Get the referenced entities in the specified language.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   Entities to process.
   * @param string $language
   *   Language to be respected for retrieved entities.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Translated entities.
   */
  protected function getTranslated(array $entities, string $language): array {
    return array_map(function (EntityInterface $entity) use ($language) {
      if ($entity instanceof TranslatableInterface && $language !== $entity->language()->getId() && $entity->hasTranslation($language)) {
        $entity = $entity->getTranslation($language);
      }
      $entity->addCacheContexts(["static:language:{$language}"]);
      return $entity;
    }, $entities);
  }

  /**
   * Filter out not accessible entities.
   *
   * This is probably against the spec, but we need SOME sort of check.
   * If an entity doesn't implement hook_query_TAG_alter then we
   * can't guarantee the access check is applied.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   Entities to filter.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   Cacheability metadata for this request.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Access filtered entities.
   */
  protected function filterAccessible(array $entities, FieldContext $context): array {
    return array_filter($entities, function (EntityInterface $entity) use ($context) {
      $access = $entity->access('view', NULL, TRUE);
      $context->addCacheableDependency($access);
      if (!$access->isAllowed()) {
        return FALSE;
      }
      return TRUE;
    });
  }

}
