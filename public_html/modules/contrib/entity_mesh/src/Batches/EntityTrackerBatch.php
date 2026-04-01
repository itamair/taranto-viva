<?php

declare(strict_types=1);

namespace Drupal\entity_mesh\Batches;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Batch operations for tracking entities in Entity Mesh.
 */
class EntityTrackerBatch {

  use StringTranslationTrait;

  /**
   * Get batch object preconfigured.
   *
   * @return \Drupal\Core\Batch\BatchBuilder
   *   Batch object.
   */
  public static function getBatchObjectPreconfigured(): BatchBuilder {
    $batch = new BatchBuilder();
    $batch->setTitle(t('Tracking entities for Entity Mesh processing'))
      ->setFinishCallback([self::class, 'finished'])
      ->setInitMessage(t('Initializing entity tracking...'))
      ->setProgressMessage(t('Processing entities...'))
      ->setErrorMessage(t('An error occurred during entity tracking.'));
    return $batch;
  }

  /**
   * Get items for operation.
   *
   * @return array
   *   Array of entity IDs to process.
   */
  public static function getItemsForOperation(): array {
    $bundle_conditions = [];
    $config = \Drupal::configFactory()->getEditable('entity_mesh.settings');
    $enabled_types = $config->get('source_types') ?? [];

    if (!isset($enabled_types['node']['enabled']) || $enabled_types['node']['enabled'] === FALSE) {
      return [];
    }

    $enabled_bundles = $enabled_types['node']['bundles'] ?? [];
    if (!empty($enabled_bundles)) {
      foreach ($enabled_bundles as $bundle => $enabled) {
        if ($enabled === TRUE) {
          $bundle_conditions[] = $bundle;
        }
      }
    }

    $query = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE);

    if (!empty($bundle_conditions)) {
      $query->condition('type', $bundle_conditions, 'IN');
    }

    $nids = $query->execute();
    return $nids;
  }

  /**
   * Get count of items for operation.
   *
   * This method is optimized for performance and only counts entities
   * without loading them into memory.
   *
   * @return int
   *   Number of entities configured to be tracked.
   */
  public static function getItemsCount(): int {
    $bundle_conditions = [];
    $config = \Drupal::configFactory()->getEditable('entity_mesh.settings');
    $enabled_types = $config->get('source_types') ?? [];

    if (!isset($enabled_types['node']['enabled']) || $enabled_types['node']['enabled'] === FALSE) {
      return 0;
    }

    $enabled_bundles = $enabled_types['node']['bundles'] ?? [];
    if (!empty($enabled_bundles)) {
      foreach ($enabled_bundles as $bundle => $enabled) {
        if ($enabled === TRUE) {
          $bundle_conditions[] = $bundle;
        }
      }
    }

    $query = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE);

    if (!empty($bundle_conditions)) {
      $query->condition('type', $bundle_conditions, 'IN');
    }

    $count = $query->count()->execute();
    return (int) $count;
  }

  /**
   * Generate batch for tracking entities.
   *
   * @return \Drupal\Core\Batch\BatchBuilder
   *   Batch object.
   */
  public static function generateBatch(): BatchBuilder {
    $batch = self::getBatchObjectPreconfigured();
    $items_for_operation = self::getItemsForOperation();

    self::clearEntityMesh();
    $batch->addOperation([self::class, 'processEntities'], [$items_for_operation, 'node']);

    return $batch;
  }

  /**
   * Processes a batch of entities to add them to the tracker.
   *
   * @param array $entity_ids
   *   Array of entity IDs to process.
   * @param string $entity_type
   *   The entity type.
   * @param array $context
   *   The batch context.
   */
  public static function processEntities(array $entity_ids, string $entity_type, array &$context): void {
    $tracker_manager = \Drupal::service('entity_mesh.tracker_manager');
    $entity_type_manager = \Drupal::entityTypeManager();

    // Initialize sandbox.
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = count($entity_ids);
      $context['sandbox']['entity_ids'] = $entity_ids;
      $context['results']['added'] = 0;
      $context['results']['failed'] = 0;
      $context['results']['entity_type'] = $entity_type;
    }

    // Process entities in chunks.
    $limit = 50;
    $entity_ids_to_process = array_slice(
      $context['sandbox']['entity_ids'],
      $context['sandbox']['progress'],
      $limit
    );

    foreach ($entity_ids_to_process as $entity_id) {
      try {
        // Load the entity.
        $entity = $entity_type_manager->getStorage($entity_type)->load($entity_id);

        if ($entity) {
          // Add to tracker.
          $result = $tracker_manager->addTrackedEntity($entity);

          if ($result) {
            $context['results']['added']++;
          }
          else {
            $context['results']['failed']++;
          }
        }
        else {
          $context['results']['failed']++;
        }
      }
      catch (\Exception $e) {
        $context['results']['failed']++;
        \Drupal::logger('entity_mesh')->error('Error tracking entity @type:@id: @message', [
          '@type' => $entity_type,
          '@id' => $entity_id,
          '@message' => $e->getMessage(),
        ]);
      }

      $context['sandbox']['progress']++;
      $context['message'] = t('Processing @current of @total entities...', [
        '@current' => $context['sandbox']['progress'],
        '@total' => $context['sandbox']['max'],
      ]);
    }

    // Update progress.
    if ($context['sandbox']['progress'] < $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
    else {
      $context['finished'] = 1;
    }
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   TRUE if batch completed successfully.
   * @param array $results
   *   The results array.
   * @param array $operations
   *   The operations array.
   */
  public static function finished(bool $success, array $results, array $operations): void {
    $messenger = \Drupal::messenger();

    if ($success) {
      $messenger->addStatus(t('Successfully added @added entities to tracker for processing.', [
        '@added' => $results['added'] ?? 0,
      ]));

      if (!empty($results['failed'])) {
        $messenger->addWarning(t('Failed to add @failed entities to tracker.', [
          '@failed' => $results['failed'],
        ]));
      }
    }
    else {
      $messenger->addError(t('An error occurred while processing entities.'));
    }
  }

  /**
   * Clear entity mesh tables.
   */
  public static function clearEntityMesh() {
    \Drupal::service('entity_mesh.tracker')->truncate();
    \Drupal::service('entity_mesh.repository')->deleteSourceByType('node', []);
  }

}
