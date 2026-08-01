<?php

namespace Drupal\custom_module\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Commands\DrushCommands;

/**
 * Drush commands.
 *
 * Provide Drush commands for custom_module.
 */
class CustomDrushCommands extends DrushCommands {

  /**
   * Constructs a new Drush commands class instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity type manager service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entity_type_manager,
  ) {
    parent::__construct();
  }

  /**
   * Remove Geo Images Media & Node Entities created via Media Library Importer.
   *
   * @command geo-images:delete
   * @aliases gid
   */
  public function geoImagesDelete(): void {
    try {
      $media_storage = \Drupal::entityTypeManager()->getStorage('media');
      $query_media = $media_storage->getQuery()
        ->condition('bundle', ['geoimage', 'geo_image'], 'IN')
        ->condition('field_generate_host_content', 1)
        ->accessCheck(FALSE);
      $geomedia_ids = $query_media->execute();
      if ($geomedia_ids) {
        $geomedias = $media_storage->loadMultiple($geomedia_ids);
        foreach ($geomedias as $geomedia) {
          $geomedia->delete();
        }
      }
    }
    catch (\Exception $e) {
    }

    try {
      $node_storage = \Drupal::entityTypeManager()->getStorage('node');
      $query_node = $node_storage->getQuery()
        ->condition('type', ['geoimage', 'geo_image'], 'IN')
        ->accessCheck(FALSE);
      $node_ids = $query_node->execute();
      if ($node_ids) {
        $nodes = $node_storage->loadMultiple($node_ids);
        foreach ($nodes as $node) {
          $node->delete();
        }
      }
    }
    catch (\Exception $e) {
    }
  }

}
