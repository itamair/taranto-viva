<?php

declare(strict_types=1);

namespace Drupal\entity_mesh;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Service for managing entity tracking operations.
 */
class TrackerManager implements TrackerManagerInterface {

  /**
   * The tracker service.
   *
   * @var \Drupal\entity_mesh\TrackerInterface
   */
  protected $tracker;

  /**
   * Constructs a TrackerManager object.
   *
   * @param \Drupal\entity_mesh\TrackerInterface $tracker
   *   The tracker service.
   */
  public function __construct(TrackerInterface $tracker) {
    $this->tracker = $tracker;
  }

  /**
   * {@inheritdoc}
   */
  public function addEntityToTracker(ContentEntityInterface $entity, int $operation): bool {
    $data = $this->preprocessEntity($entity, $operation);
    return $this->tracker->addEntity(
      $data['entity_type'],
      $data['entity_id'],
      $data['operation']
    );
  }

  /**
   * Adds an entity to the tracker for processing.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to add.
   *
   * @return bool
   *   TRUE if the entity was added successfully, FALSE otherwise.
   */
  public function addTrackedEntity(ContentEntityInterface $entity): bool {
    return $this->addEntityToTracker($entity, TrackerInterface::OPERATION_PROCESS);
  }

  /**
   * Adds an entity to the tracker for deletion.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to add.
   *
   * @return bool
   *   TRUE if the entity was added successfully, FALSE otherwise.
   */
  public function deleteTrackedEntity(ContentEntityInterface $entity): bool {
    return $this->addEntityToTracker($entity, TrackerInterface::OPERATION_DELETE);
  }

  /**
   * Adds multiple entities to the tracker for processing.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface[] $entities
   *   An array of entities to add.
   *
   * @return bool
   *   TRUE if the entities were added successfully, FALSE otherwise.
   */
  public function addTrackedEntities(array $entities): bool {
    $data = $this->preprocessEntities($entities, TrackerInterface::OPERATION_PROCESS);
    return $this->tracker->addMultipleEntities($data);
  }

  /**
   * Preprocesses an entity to extract tracking data.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to preprocess.
   * @param int $operation
   *   The operation to perform.
   *
   * @return array
   *   An array with keys:
   *   - entity_type: The entity type ID.
   *   - entity_id: The entity ID.
   *   - operation: The operation to perform.
   */
  protected function preprocessEntity(ContentEntityInterface $entity, int $operation): array {
    return [
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
      'operation' => $operation,
    ];
  }

  /**
   * Preprocesses multiple entities to extract tracking data.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface[] $entities
   *   An array of entities to preprocess.
   * @param int $operation
   *   The operation to perform.
   *
   * @return array
   *   An array of arrays, each with keys:
   *   - entity_type: The entity type ID.
   *   - entity_id: The entity ID.
   *   - operation: The operation to perform.
   */
  protected function preprocessEntities(array $entities, int $operation): array {
    $result = [];
    foreach ($entities as $entity) {
      $result[] = $this->preprocessEntity($entity, $operation);
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatusPending(ContentEntityInterface $entity): bool {
    $tracker_id = $this->tracker->getIdTracker(
      $entity->getEntityTypeId(),
      $entity->id()
    );

    if ($tracker_id === FALSE) {
      return $this->tracker->addEntity(
        $entity->getEntityTypeId(),
        $entity->id(),
        TrackerInterface::OPERATION_PROCESS,
        TrackerInterface::STATUS_PENDING
      );
    }

    return $this->tracker->updateStatus($tracker_id, TrackerInterface::STATUS_PENDING);
  }

  /**
   * {@inheritdoc}
   */
  public function setStatusProcessing(ContentEntityInterface $entity): bool {
    $tracker_id = $this->tracker->getIdTracker(
      $entity->getEntityTypeId(),
      $entity->id()
    );

    if ($tracker_id === FALSE) {
      return $this->tracker->addEntity(
        $entity->getEntityTypeId(),
        $entity->id(),
        TrackerInterface::OPERATION_PROCESS,
        TrackerInterface::STATUS_PROCESSING
      );
    }

    return $this->tracker->updateStatus($tracker_id, TrackerInterface::STATUS_PROCESSING);
  }

  /**
   * {@inheritdoc}
   */
  public function setStatusProcessed(ContentEntityInterface $entity): bool {
    $tracker_id = $this->tracker->getIdTracker(
      $entity->getEntityTypeId(),
      $entity->id()
    );

    if ($tracker_id === FALSE) {
      return $this->tracker->addEntity(
        $entity->getEntityTypeId(),
        $entity->id(),
        TrackerInterface::OPERATION_PROCESS,
        TrackerInterface::STATUS_PROCESSED
      );
    }

    return $this->tracker->updateStatus($tracker_id, TrackerInterface::STATUS_PROCESSED);
  }

  /**
   * {@inheritdoc}
   */
  public function setStatusFailed(ContentEntityInterface $entity): bool {
    $tracker_id = $this->tracker->getIdTracker(
      $entity->getEntityTypeId(),
      $entity->id()
    );

    if ($tracker_id === FALSE) {
      return $this->tracker->addEntity(
        $entity->getEntityTypeId(),
        $entity->id(),
        TrackerInterface::OPERATION_PROCESS,
        TrackerInterface::STATUS_FAILED
      );
    }

    return $this->tracker->markAsFailed($tracker_id);
  }

}
