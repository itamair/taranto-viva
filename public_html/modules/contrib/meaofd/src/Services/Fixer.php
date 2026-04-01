<?php

namespace Drupal\meaofd\Services;

use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service to fix mismatched entity and/or field definitions.
 *
 * This service is designed to handle inconsistencies between the defined
 * entity types and their fields in the system and actual stored definitions.
 * It ensures that the entity definitions are correctly installed or updated.
 */
class Fixer {

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity definition update manager service.
   *
   * @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface
   */
  protected $entityDefinitionUpdateManager;

  /**
   * Constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface $entity_definition_update_manager
   *   The entity definition update manager service.
   */
  public function __construct(LoggerInterface $logger, EntityTypeManagerInterface $entity_type_manager, EntityDefinitionUpdateManagerInterface $entity_definition_update_manager) {
    $this->entityDefinitionUpdateManager = $entity_definition_update_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
  }

  /**
   * Gets a human readable summary of the detected changes.
   *
   * This is an extension of the
   * EntityDefinitionUpdateManager::getChangeSummary() method.
   *
   * @return array
   *   An associative array keyed by entity type id. Each entry is an array of
   *   human-readable strings, each describing a change.
   */
  public function getChangeSummary(): array {
    return $this->entityDefinitionUpdateManager->getChangeSummary();
  }

  /**
   * Fixes mismatched entity definitions for a specific entity type.
   *
   * This method reviews the entity definition changes and installs or updates
   * the entity type if necessary. Optionally, it can also rebuild the entity
   * cache definitions before and after the update.
   *
   * @param string $target_entity_type_id
   *   The entity type ID that should be checked and fixed.
   * @param bool $rebuild_entity_cache_definitions
   *   (optional) Whether to rebuild entity cache definitions before and after
   *   the fix. Defaults to TRUE.
   * @param bool $log_events
   *   (optional) Whether to log events like successful updates or errors.
   *   Defaults to TRUE.
   *
   * @return array
   *   An array of entity type IDs that were installed or updated.
   */
  public function fix(string $target_entity_type_id, bool $rebuild_entity_cache_definitions = TRUE, bool $log_events = TRUE): array {
    $installed_or_updated_entities = [];

    // Optionally rebuild entity cache definitions before checking.
    if ($rebuild_entity_cache_definitions) {
      $this->rebuildEntityCacheDefinitions($log_events);
    }

    // Fix the specified entity type if it has changes.
    try {
      if ($this->entityTypeHasChanges($target_entity_type_id)) {
        $entity_type = $this->entityTypeManager->getDefinition($target_entity_type_id);
        $this->entityDefinitionUpdateManager->installEntityType($entity_type);
        $installed_or_updated_entities[] = $target_entity_type_id;

        if ($log_events) {
          $this->logger->info('Entity type @entity_type was installed or updated.', ['@entity_type' => $target_entity_type_id]);
        }
      }
      elseif ($log_events) {
        $this->logger->info('No changes detected for entity type @entity_type. No action taken.', ['@entity_type' => $target_entity_type_id]);
      }
    }
    catch (\Exception $e) {
      $this->logger->error("An error occurred while fixing entity type '@entity_type': @message", [
        '@entity_type' => $target_entity_type_id,
        '@message' => $e->getMessage(),
      ]);
    }

    // Optionally rebuild entity cache definitions again after updates.
    if ($rebuild_entity_cache_definitions && !empty($installed_or_updated_entities)) {
      $this->rebuildEntityCacheDefinitions($log_events);
    }

    return $installed_or_updated_entities;
  }

  /**
   * Checks if a specific entity type has changes that need to be fixed.
   *
   * @param string $entity_type_id
   *   The entity type ID to check for changes.
   *
   * @return bool
   *   TRUE if the entity type has changes, FALSE otherwise.
   */
  public function entityTypeHasChanges(string $entity_type_id): bool {
    return array_key_exists($entity_type_id, $this->getChangeSummary() ?? []);
  }

  /**
   * Rebuilds the cached entity type definitions.
   *
   * This method clears the cached entity type definitions, ensuring that
   * any changes made to the entity types are reflected immediately.
   *
   * @param bool $log_events
   *   (optional) Whether to log events during the cache rebuild process.
   */
  protected function rebuildEntityCacheDefinitions(bool $log_events = TRUE): void {
    try {
      $this->entityTypeManager->clearCachedDefinitions();

      if ($log_events) {
        $this->logger->info('Entity cache definitions have been rebuilt.');
      }
    }
    catch (\Exception $e) {
      $this->logger->error('An error occurred while rebuilding entity cache definitions: @message', ['@message' => $e->getMessage()]);
    }
  }

}
