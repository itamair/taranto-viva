<?php

namespace Drupal\react_maplibre_map\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a React Maplibre Block component.
 *
 * @Block(
 * id = "react_maplibre_map",
 * admin_label = @Translation ("React Maplibre Block"),
 * )
 */
class ReactMaplibreBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => "<div id='react_maplibre_map'></div>",
      '#attached' => [
        'library' => [
          'react_maplibre_map/react_maplibre_map_prod',
        ],
      ],
    ];
  }

}
