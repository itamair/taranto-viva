<?php

use Drupal\Component\Utility\NestedArray;

/**
 * @file
 * Post update hooks related to views_geojson module.
 */

/**
 * Add id_field data source to existing geojson_export view displays.
 */
function views_geojson_post_update_add_id_field_5(&$sandbox = NULL) {

  // Load all views that have a geojson_export display.
  /** @var \Drupal\views\ViewEntityInterface $geojson_views */
  $geojson_views = \Drupal::entityTypeManager()->getStorage('view')->loadByProperties([
    'display.*.display_plugin' => 'geojson_export',
  ]);
  foreach ($geojson_views as $view) {

    // Keep track if we need to update the view.
    $update = FALSE;

    // Check each display.
    $displays = $view->get('display');
    foreach ($displays as &$display) {

      // Skip displays that do not use geojson_export.
      if ($display['display_plugin'] != 'geojson_export') {
        continue;
      }

      // Skip displays that do not use geojson display style.
      if (NestedArray::getValue($display, ['display_options', 'style', 'type']) != 'geojson') {
        continue;
      }

      // Set the nested id_field to an empty string if it does not exist.
      if (NestedArray::getValue($display, ['display_options', 'style', 'options', 'data_source', 'id_field']) === NULL) {
        NestedArray::setValue($display, ['display_options', 'style', 'options', 'data_source', 'id_field'], '');
        $update = TRUE;
      }
    }

    // Update the views displays if changes were made.
    if ($update) {
      $view->set('display', $displays);
      $view->save();
    }
  }

}
