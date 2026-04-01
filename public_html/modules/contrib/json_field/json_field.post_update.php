<?php

/**
 * @file
 * Implementations of hook_post_update_NAME() for JSON Field.
 */

/**
 * Empty update script to force the system to reload the field_type_categories.
 */
function json_field_post_update_fix_field_size() {
  // Loads all field storage configurations of type 'json'.
  $field_storages = \Drupal::entityTypeManager()
    ->getStorage('field_storage_config')
    ->loadByProperties(['type' => 'json']);

  foreach ($field_storages as $field_storage) {
    $settings = $field_storage->getSettings();

    // Ensure the 'size' setting exists and is set to 16384.
    if (isset($settings['size']) && $settings['size'] == 16384) {
      // Sets the size to 65535.
      $settings['size'] = 65535;
      $field_storage->setSettings($settings);

      try {
        $field_storage->save();
        \Drupal::logger('json_field')->info('Updated field storage: @field', [
          '@field' => $field_storage->getName(),
        ]);
      }
      catch (Exception $e) {
        \Drupal::logger('json_field')->error('Failed to update field storage @field: @error', [
          '@field' => $field_storage->getName(),
          '@error' => $e->getMessage(),
        ]);
      }
    }
  }
}
