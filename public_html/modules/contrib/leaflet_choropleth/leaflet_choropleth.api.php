<?php

/**
 * @file
 * Hook documentation for leaflet_choropleth module.
 */

use Drupal\views\Plugin\views\ViewsPluginInterface;

/**
 * Allow other modules to add/alter the $color_scale.
 *
 * @param array $color_scale
 *   The original $color_scale array.
 * @param \Drupal\views\Plugin\views\ViewsPluginInterface $view_style
 *   The Leaflet Map View Style.
 */
function hook_leaflet_choropleth_color_scale_alter(array &$color_scale, ViewsPluginInterface $view_style): void {
  /*
   * Make custom alterations to the $color_scale.
   * Let's imagine we want to alter the Legend items values with fixed values
   * coming from a vocabulary named "choropleth_categories", the we could
   * implement the following:
   *
   * if ($view_style->getPluginId() == 'XXX'
   * && $view_style->view->current_display === 'XXX') {
   * $categories = [];
   * $vid = \Drupal::entityTypeManager()
   * ->getStorage('taxonomy_term')->loadByProperties([
   * 'vid' => 'choropleth_categories',
   * ]);
   * $categories[] = $term->getName();
   * }
   * foreach ($color_scale["legend_items"] as $k => $legend_item) {
   * $color_scale["legend_items"][$k]['values']['value'] = $categories[$k];
   * }
   *
   * foreach ($color_scale["legend_items"] as $k => $legend_item) {
   *   $color_scale["legend_items"][$k]['values']['value'] = $categories[$k];
   * }
   * }
   *
   */
}
