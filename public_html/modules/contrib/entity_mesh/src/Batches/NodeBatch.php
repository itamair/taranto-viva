<?php

namespace Drupal\entity_mesh\Batches;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\entity_mesh\TrackerInterface;
use Drupal\node\NodeInterface;

/**
 * Batch for processing nodes from tracker.
 */
class NodeBatch {

  /**
   * Get batch object preconfigured.
   *
   * @return \Drupal\Core\Batch\BatchBuilder
   *   Batch object.
   */
  public static function getBatchObjectPreconfigured(): BatchBuilder {
    $batch = new BatchBuilder();
    $batch->setTitle(t('Processing tracked nodes'))
      ->setFinishCallback([self::class, 'finished'])
      ->setInitMessage(t('Initializing node processing...'))
      ->setProgressMessage(t('Processing nodes...'))
      ->setErrorMessage(t('An error occurred during node processing.'));
    return $batch;
  }

  /**
   * Get items for operation from the tracker.
   *
   * Retrieves all pending node entities from the tracker queue that need
   * to be processed by the batch operation.
   *
   * @return array
   *   Array of tracker items, each containing:
   *   - id: The tracker record ID.
   *   - entity_type: The entity type (always 'node' in this case).
   *   - entity_id: The node ID.
   *   - operation: The operation type (OPERATION_PROCESS or OPERATION_DELETE).
   *   - status: The current processing status.
   *   - timestamp: When the item was added to the tracker.
   *   - retry_count: Number of failed processing attempts.
   */
  public static function getItemsForOperation() {
    $tracker = \Drupal::service('entity_mesh.tracker');
    $pending_entities = $tracker->getPendingEntities(NULL, 'node');

    return $pending_entities;
  }

  /**
   * Generate batch for processing tracked nodes.
   *
   * Creates a complete batch configuration that processes all pending nodes
   * from the tracker. Each tracked node will be processed individually by
   * the operation() method.
   *
   * @return \Drupal\Core\Batch\BatchBuilder
   *   A configured batch builder object ready to be executed. The batch will
   *   process all pending nodes from the tracker queue, updating their status
   *   as they are processed.
   */
  public static function generateBatch(): BatchBuilder {
    $batch = self::getBatchObjectPreconfigured();
    $items_for_operation = self::getItemsForOperation();

    foreach ($items_for_operation as $item) {
      $batch->addOperation([self::class, 'operation'], [$item]);
    }

    return $batch;
  }

  /**
   * Process a single tracked node.
   *
   * This is the main batch operation that processes a single node from the
   * tracker queue. It handles both OPERATION_PROCESS and OPERATION_DELETE
   * operations, updating the tracker status accordingly.
   *
   * The method will:
   * - Load the node from the database
   * - Execute the appropriate operation (process or delete)
   * - Mark the tracker item as processed on success
   * - Mark the tracker item as failed on error
   * - Log any errors that occur during processing
   *
   * @param array $tracker_item
   *   The tracker item array containing:
   *   - id: The tracker record ID (used for updating status).
   *   - entity_id: The node ID to process.
   *   - operation: The operation type (OPERATION_PROCESS or OPERATION_DELETE).
   *   - Other tracker fields (entity_type, status, timestamp, retry_count).
   * @param array $context
   *   The batch context array, used to track:
   *   - results['processed']: Array of successfully processed node IDs.
   *   - results['failed']: Array of failed node IDs.
   *   - message: Current operation message displayed to the user.
   *   - sandbox['progress']: Number of items processed so far.
   */
  public static function operation(array $tracker_item, array &$context) {
    $tracker = \Drupal::service('entity_mesh.tracker');
    $tracker_id = (int) $tracker_item['id'];
    $entity_id = $tracker_item['entity_id'];
    $operation = (int) $tracker_item['operation'];

    try {
      // Load the node.
      $node = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->load($entity_id);

      if ($node instanceof NodeInterface) {
        // Check operation type.
        if ($operation === TrackerInterface::OPERATION_PROCESS) {
          // Clear mesh account cache before processing to ensure fresh config.
          \Drupal::service('entity_mesh.repository')->clearMeshAccountCache();

          // Process the entity.
          \Drupal::service('entity_mesh.entity_render')->processEntity($node);
        }

        // Mark as processed.
        $tracker->markAsProcessed($tracker_id);
        $context['results']['processed'][] = $entity_id;
      }
      else {
        // Node not found, mark as failed.
        $tracker->markAsFailed($tracker_id);
        $context['results']['failed'][] = $entity_id;
      }
    }
    catch (\Exception $e) {
      // Mark as failed on exception.
      $tracker->markAsFailed($tracker_id);
      $context['results']['failed'][] = $entity_id;
      \Drupal::logger('entity_mesh')->error('Error processing node @id: @message', [
        '@id' => $entity_id,
        '@message' => $e->getMessage(),
      ]);
    }

    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
    }
    $context['sandbox']['progress']++;

    $context['message'] = t('Processing node @id from tracker...', ['@id' => $entity_id]);
  }

  /**
   * Batch finished callback.
   *
   * Called when the batch processing is complete. Displays appropriate
   * messages to the user based on the processing results.
   *
   * @param bool $success
   *   TRUE if the batch completed successfully, FALSE if there was a fatal
   *   error that stopped batch processing.
   * @param array $results
   *   The results array accumulated during batch processing:
   *   - processed: Array of node IDs that were successfully processed.
   *   - failed: Array of node IDs that failed to process.
   * @param array $operations
   *   Array of operations that were executed (or remaining if batch failed).
   */
  public static function finished(bool $success, array $results, array $operations): void {
    $messenger = \Drupal::messenger();

    if ($success) {
      $processed = count($results['processed'] ?? []);
      $failed = count($results['failed'] ?? []);

      $messenger->addStatus(t('Successfully processed @count nodes.', [
        '@count' => $processed,
      ]));

      if ($failed > 0) {
        $messenger->addWarning(t('Failed to process @count nodes.', [
          '@count' => $failed,
        ]));
      }
    }
    else {
      $messenger->addError(t('An error occurred while processing nodes.'));
    }
  }

}
