<?php

namespace Drupal\entity_mesh;

use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;

/**
 * Tracker service for Entity Mesh.
 *
 * Tracks entities that need to be processed by entity_mesh.
 */
class Tracker implements TrackerInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a Tracker object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(Connection $database, TimeInterface $time) {
    $this->database = $database;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public function addEntity(string $entity_type, string $entity_id, int $operation, int $status = self::STATUS_PENDING): bool {
    try {
      $this->database->merge('entity_mesh_tracker')
        ->keys([
          'entity_type' => $entity_type,
          'entity_id' => $entity_id,
        ])
        ->fields([
          'operation' => $operation,
          'status' => $status,
          'timestamp' => $this->time->getRequestTime(),
          'retry_count' => 0,
        ])
        ->execute();

      return TRUE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getPendingEntities(?int $limit = NULL, ?string $entity_type = NULL): array {
    $query = $this->database->select('entity_mesh_tracker', 't')
      ->fields('t', [
        'id',
        'entity_type',
        'entity_id',
        'operation',
        'status',
        'timestamp',
        'retry_count',
      ])
      ->condition('status', self::STATUS_PENDING)
      ->orderBy('timestamp', 'ASC');

    if ($entity_type !== NULL) {
      $query->condition('entity_type', $entity_type);
    }

    if ($limit !== NULL) {
      $query->range(0, $limit);
    }

    $result = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $result ?: [];
  }

  /**
   * {@inheritdoc}
   */
  public function markAsProcessed(int $tracker_id): bool {
    try {
      $this->database->update('entity_mesh_tracker')
        ->fields([
          'status' => self::STATUS_PROCESSED,
          'timestamp' => $this->time->getRequestTime(),
        ])
        ->condition('id', $tracker_id)
        ->execute();

      return TRUE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function markAsFailed(int $tracker_id): bool {
    try {
      // Get current retry count.
      $current_retry = $this->database->select('entity_mesh_tracker', 't')
        ->fields('t', ['retry_count'])
        ->condition('id', $tracker_id)
        ->execute()
        ->fetchField();

      // Update status and increment retry count.
      $this->database->update('entity_mesh_tracker')
        ->fields([
          'status' => self::STATUS_FAILED,
          'timestamp' => $this->time->getRequestTime(),
          'retry_count' => ($current_retry ?? 0) + 1,
        ])
        ->condition('id', $tracker_id)
        ->execute();

      return TRUE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFailedEntities(?int $limit = NULL, ?int $max_retries = NULL): array {
    $query = $this->database->select('entity_mesh_tracker', 't')
      ->fields('t', [
        'id',
        'entity_type',
        'entity_id',
        'operation',
        'status',
        'timestamp',
        'retry_count',
      ])
      ->condition('status', self::STATUS_FAILED)
      ->orderBy('timestamp', 'ASC');

    if ($max_retries !== NULL) {
      $query->condition('retry_count', $max_retries, '<');
    }

    if ($limit !== NULL) {
      $query->range(0, $limit);
    }

    $result = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $result ?: [];
  }

  /**
   * {@inheritdoc}
   */
  public function deleteProcessedRecords(int $days): int {
    $threshold = $this->time->getRequestTime() - ($days * 24 * 60 * 60);

    $deleted = $this->database->delete('entity_mesh_tracker')
      ->condition('status', self::STATUS_PROCESSED)
      ->condition('timestamp', $threshold, '<')
      ->execute();

    return $deleted;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteEntity(string $entity_type, string $entity_id): bool {
    try {
      $this->database->delete('entity_mesh_tracker')
        ->condition('entity_type', $entity_type)
        ->condition('entity_id', $entity_id)
        ->execute();

      return TRUE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getPendingCount(?string $entity_type = NULL): int {
    $query = $this->database->select('entity_mesh_tracker', 't')
      ->condition('status', self::STATUS_PENDING);

    if ($entity_type !== NULL) {
      $query->condition('entity_type', $entity_type);
    }

    $count = $query->countQuery()->execute()->fetchField();

    return (int) $count;
  }

  /**
   * {@inheritdoc}
   */
  public function getTotalCount(?string $entity_type = NULL): int {
    $query = $this->database->select('entity_mesh_tracker', 't');

    if ($entity_type !== NULL) {
      $query->condition('entity_type', $entity_type);
    }

    $count = $query->countQuery()->execute()->fetchField();

    return (int) $count;
  }

  /**
   * {@inheritdoc}
   */
  public function truncate(): bool {
    try {
      $this->database->truncate('entity_mesh_tracker')->execute();
      return TRUE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addMultipleEntities(array $entities): bool {
    // Return TRUE if empty array.
    if (empty($entities)) {
      return TRUE;
    }

    try {
      $transaction = $this->database->startTransaction();

      try {
        foreach ($entities as $entity) {
          $this->database->merge('entity_mesh_tracker')
            ->keys([
              'entity_type' => $entity['entity_type'],
              'entity_id' => $entity['entity_id'],
            ])
            ->fields([
              'operation' => $entity['operation'],
              'status' => self::STATUS_PENDING,
              'timestamp' => $this->time->getRequestTime(),
              'retry_count' => 0,
            ])
            ->execute();
        }

        return TRUE;
      }
      catch (\Exception $e) {
        $transaction->rollBack();
        return FALSE;
      }
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getIdTracker(string $entity_type, string $entity_id) {
    try {
      $id = $this->database->select('entity_mesh_tracker', 't')
        ->fields('t', ['id'])
        ->condition('entity_type', $entity_type)
        ->condition('entity_id', $entity_id)
        ->execute()
        ->fetchField();

      return $id !== FALSE ? (int) $id : FALSE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function updateStatus(int $tracker_id, int $status): bool {
    try {
      $this->database->update('entity_mesh_tracker')
        ->fields([
          'status' => $status,
          'timestamp' => $this->time->getRequestTime(),
        ])
        ->condition('id', $tracker_id)
        ->execute();

      return TRUE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

}
