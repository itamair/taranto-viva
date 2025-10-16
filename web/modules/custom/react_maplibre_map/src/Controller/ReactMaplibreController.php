<?php

namespace Drupal\react_maplibre_map\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for rendering the React MapLibre map.
 */
class ReactMaplibreController extends ControllerBase {

  /**
   * Renders the React MapLibre map block.
   *
   * @return array
   *   A render array containing the block.
   */
  public function viewMap(): array {
    $block_manager = \Drupal::service('plugin.manager.block');

    try {
      // Load the react_maplibre_map block.
      $config = [];
      $plugin_block = $block_manager->createInstance('react_maplibre_map', $config);
      $build = $plugin_block->build();
    }
    catch (\Exception $e) {
      $build = [];
    }

    // Return the block build.
    return $build;
  }

}
