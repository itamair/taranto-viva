<?php

namespace Drupal\meaofd\Commands;

use Drupal\meaofd\Services\Fixer;
use Drush\Commands\DrushCommands;

/**
 * Defines a Drush command for fixing mismatched entity definitions.
 */
class Commands extends DrushCommands {

  /**
   * The Fixer service.
   *
   * @var \Drupal\meaofd\Services\Fixer
   */
  protected $fixer;

  /**
   * Constructor.
   *
   * @param \Drupal\meaofd\Services\Fixer $fixer
   *   The Fixer service.
   */
  public function __construct(Fixer $fixer) {
    parent::__construct();
    $this->fixer = $fixer;
  }

  /**
   * Fixes mismatched entity definitions for a given entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID to be fixed.
   * @param array $options
   *   The options array to pass flags like --no-cache-rebuild.
   *
   * @command meaofd:fix
   *
   * @option no-cache-rebuild
   *   If set, the cache won't be rebuilt before and after the fix.
   *
   * @usage meaofd:fix node
   *   Fixes the mismatched definitions for the node entity type.
   */
  public function fixEntity(string $entity_type_id, array $options = ['no-cache-rebuild' => FALSE]): void {
    $rebuild_cache = !$options['no-cache-rebuild'];

    $this->output()->writeln('Fixing entity definitions for ' . $entity_type_id);

    try {
      // Use the Fixer service to fix the entity type.
      $updated_entities = ($this->fixer->entityTypeHasChanges($entity_type_id)) ? $this->fixer->fix($entity_type_id, $rebuild_cache) : NULL;

      if (!empty($updated_entities)) {
        $this->output()->writeln('Entity types updated: ' . implode(', ', $updated_entities));
      }
      else {
        $this->output()->writeln('No updates required for entity type: ' . $entity_type_id);
      }
    }
    catch (\Exception $e) {
      $this->logger->error('An error occurred: ' . $e->getMessage());
    }
  }

}
