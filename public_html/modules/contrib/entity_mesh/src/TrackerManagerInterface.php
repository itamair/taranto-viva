<?php

declare(strict_types=1);

namespace Drupal\entity_mesh;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Interface for the Entity Mesh Tracker Manager service.
 */
interface TrackerManagerInterface {

  /**
   * Processes an entity and adds it to the tracker.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to process.
   * @param int $operation
   *   The operation to perform (OPERATION_PROCESS or OPERATION_DELETE).
   *
   * @return bool
   *   TRUE if the entity was added successfully, FALSE otherwise.
   */
  public function addEntityToTracker(ContentEntityInterface $entity, int $operation): bool;

  /**
   * Adds an entity to the tracker for processing.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to add.
   *
   * @return bool
   *   TRUE if the entity was added successfully, FALSE otherwise.
   */
  public function addTrackedEntity(ContentEntityInterface $entity): bool;

  /**
   * Adds an entity to the tracker for deletion.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to add.
   *
   * @return bool
   *   TRUE if the entity was added successfully, FALSE otherwise.
   */
  public function deleteTrackedEntity(ContentEntityInterface $entity): bool;

  /**
   * Adds multiple entities to the tracker for processing.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface[] $entities
   *   An array of entities to add.
   *
   * @return bool
   *   TRUE if the entities were added successfully, FALSE otherwise.
   */
  public function addTrackedEntities(array $entities): bool;

  /**
   * Sets an entity status to pending in the tracker.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to update.
   *
   * @return bool
   *   TRUE if the status was updated successfully, FALSE otherwise.
   */
  public function setStatusPending(ContentEntityInterface $entity): bool;

  /**
   * Sets an entity status to processing in the tracker.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to update.
   *
   * @return bool
   *   TRUE if the status was updated successfully, FALSE otherwise.
   */
  public function setStatusProcessing(ContentEntityInterface $entity): bool;

  /**
   * Sets an entity status to processed in the tracker.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to update.
   *
   * @return bool
   *   TRUE if the status was updated successfully, FALSE otherwise.
   */
  public function setStatusProcessed(ContentEntityInterface $entity): bool;

  /**
   * Sets an entity status to failed in the tracker.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to update.
   *
   * @return bool
   *   TRUE if the status was updated successfully, FALSE otherwise.
   */
  public function setStatusFailed(ContentEntityInterface $entity): bool;

}
