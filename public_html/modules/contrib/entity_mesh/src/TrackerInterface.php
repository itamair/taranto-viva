<?php

namespace Drupal\entity_mesh;

/**
 * Interface for the Entity Mesh Tracker service.
 *
 * Provides methods to track entities that need to be processed by entity_mesh.
 */
interface TrackerInterface {

  /**
   * Operation: Process entity (insert/update).
   */
  const OPERATION_PROCESS = 1;

  /**
   * Operation: Delete entity.
   */
  const OPERATION_DELETE = 2;

  /**
   * Status: Entity is pending processing.
   */
  const STATUS_PENDING = 1;

  /**
   * Status: Entity is currently being processed.
   */
  const STATUS_PROCESSING = 2;

  /**
   * Status: Entity has been successfully processed.
   */
  const STATUS_PROCESSED = 3;

  /**
   * Status: Entity processing failed.
   */
  const STATUS_FAILED = 4;

  /**
   * Adds an entity to the tracking table.
   *
   * If the entity already exists in the tracker, it updates the operation
   * and resets the status to pending.
   *
   * @param string $entity_type
   *   The entity type (e.g., 'node', 'taxonomy_term').
   * @param string $entity_id
   *   The entity ID.
   * @param int $operation
   *   The operation to perform (OPERATION_PROCESS or OPERATION_DELETE).
   * @param int $status
   *   The status of the entity (defaults to STATUS_PENDING).
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  public function addEntity(string $entity_type, string $entity_id, int $operation, int $status = self::STATUS_PENDING): bool;

  /**
   * Gets pending entities from the tracking table.
   *
   * @param int|null $limit
   *   Optional limit on the number of entities to return.
   *   If NULL, returns all pending entities.
   * @param string|null $entity_type
   *   Optional filter by entity type.
   *
   * @return array
   *   Array of pending entities with keys:
   *   - id: Tracker record ID
   *   - entity_type: Entity type
   *   - entity_id: Entity ID
   *   - operation: Operation to perform
   *   - status: Current status
   *   - created: Creation timestamp
   *   - retry_count: Number of retry attempts.
   */
  public function getPendingEntities(?int $limit = NULL, ?string $entity_type = NULL): array;

  /**
   * Marks an entity as successfully processed.
   *
   * @param int $tracker_id
   *   The tracker record ID.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  public function markAsProcessed(int $tracker_id): bool;

  /**
   * Marks an entity as failed.
   *
   * Increments the retry count.
   *
   * @param int $tracker_id
   *   The tracker record ID.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  public function markAsFailed(int $tracker_id): bool;

  /**
   * Gets failed entities from the tracking table.
   *
   * @param int|null $limit
   *   Optional limit on the number of entities to return.
   * @param int|null $max_retries
   *   Optional filter to only return entities with retry_count less than this
   *   value.
   *
   * @return array
   *   Array of failed entities with keys:
   *   - id: Tracker record ID
   *   - entity_type: Entity type
   *   - entity_id: Entity ID
   *   - operation: Operation to perform
   *   - status: Current status
   *   - created: Creation timestamp
   *   - retry_count: Number of retry attempts.
   */
  public function getFailedEntities(?int $limit = NULL, ?int $max_retries = NULL): array;

  /**
   * Deletes processed records older than specified days.
   *
   * @param int $days
   *   Delete records processed more than this many days ago.
   *
   * @return int
   *   Number of records deleted.
   */
  public function deleteProcessedRecords(int $days): int;

  /**
   * Deletes an entity from the tracking table.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $entity_id
   *   The entity ID.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  public function deleteEntity(string $entity_type, string $entity_id): bool;

  /**
   * Gets count of pending entities.
   *
   * @param string|null $entity_type
   *   Optional filter by entity type.
   *
   * @return int
   *   Number of pending entities.
   */
  public function getPendingCount(?string $entity_type = NULL): int;

  /**
   * Gets total count of tracked entities.
   *
   * Returns the total number of entities in the tracker, regardless of status.
   *
   * @param string|null $entity_type
   *   Optional filter by entity type.
   *
   * @return int
   *   Total number of tracked entities.
   */
  public function getTotalCount(?string $entity_type = NULL): int;

  /**
   * Truncates the tracker table.
   *
   * Removes all records from the tracking table.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  public function truncate(): bool;

  /**
   * Adds multiple entities to the tracking table at once.
   *
   * @param array $entities
   *   Array of entities to add. Each element should be an array with keys:
   *   - entity_type: The entity type
   *   - entity_id: The entity ID
   *   - operation: The operation to perform (OPERATION_PROCESS or
   *     OPERATION_DELETE).
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  public function addMultipleEntities(array $entities): bool;

  /**
   * Gets the tracker ID for a given entity.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $entity_id
   *   The entity ID.
   *
   * @return int|false
   *   The tracker ID if found, FALSE otherwise.
   */
  public function getIdTracker(string $entity_type, string $entity_id);

  /**
   * Updates the status of a tracker record.
   *
   * @param int $tracker_id
   *   The tracker record ID.
   * @param int $status
   *   The new status value.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  public function updateStatus(int $tracker_id, int $status): bool;

}
