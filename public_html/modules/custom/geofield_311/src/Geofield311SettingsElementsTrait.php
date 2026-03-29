<?php

namespace Drupal\geofield_311;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class GeofieldMapFieldTrait.
 *
 * Provide common functions for Geofield 311 Settings Elements.
 */
trait Geofield311SettingsElementsTrait {

  use StringTranslationTrait;

  /**
   * Get the Default Settings.
   *
   * @return array
   *   The default settings.
   */
  public static function getGeofield311AdditionalDefaultSettings() {
    return [
      'geojson_app_limit' => [
        'bounds_zoom_flag' => FALSE,
        'bounds_zoom_correction' => 0,
        'bounds_limit_flag' => FALSE,
        'max_zoom_out' => -2,
      ],
    ];
  }

  /**
   * Generate the Leaflet Map General Settings.
   *
   * @param array $form
   *   The form elements.
   * @param array $settings
   *   The settings.
   */
  protected function getGeofield311AdditionalGeojsonAppLimitSettings(array &$form, array $settings) {
    $default_settings = $this->getGeofield311AdditionalDefaultSettings();

    $form['geojson_app_limit'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Geojson App Limit Specific Settings'),
    ];

    $form['geojson_app_limit']['bounds_zoom_flag'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Fit Bounds Zoom'),
      '#default_value' => $settings['geojson_app_limit']['bounds_zoom_flag'] ?? $default_settings['geojson_app_limit']['bounds_zoom_flag'],
      '#return_value' => 1,
    ];

    $form['geojson_app_limit']['bounds_zoom_correction'] = [
      '#type' => 'number',
      '#max' => 3,
      '#min' => -3,
      '#title' => $this->t('Bounds Zoom Correction'),
      '#description' => $this->t('Input a positive|negative integer value that corrects the Bounds Zoom'),
      '#default_value' => $settings['geojson_app_limit']['bounds_zoom_correction'] ?? $default_settings['geojson_app_limit']['bounds_zoom_correction'],
      '#states' => [
        'visible' => [
          [':input[name="style_options[geojson_app_limit][bounds_zoom_flag]"]' => ['checked' => TRUE]],
          'or',
          [':input[name="fields[field_geofield][settings_edit_form][settings][geojson_app_limit][bounds_zoom_flag]"]' => ['checked' => TRUE]],
        ],
      ],
    ];

    $form['geojson_app_limit']['bounds_limit_flag'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Limit to GeoJson Bounds'),
      '#default_value' => $settings['geojson_app_limit']['bounds_limit_flag'] ?? $default_settings['geojson_app_limit']['bounds_limit_flag'],
    ];

    $form['geojson_app_limit']['max_zoom_out'] = [
      '#type' => 'number',
      '#max' => 0,
      '#min' => -4,
      '#title' => $this->t('Max Zoom Out (from Bounds Zoom, with Correction)'),
      '#description' => $this->t('Input a negative integer value that defines the Max zoom out levels from the initial starting Bounds Zoom'),
      '#default_value' => $settings['geojson_app_limit']['max_zoom_out'] ?? $default_settings['geojson_app_limit']['max_zoom_out'],
      '#states' => [
        'visible' => [
          [':input[name="style_options[geojson_app_limit][bounds_limit_flag]"]' => ['checked' => TRUE]],
          'or',
          [':input[name="fields[field_geofield][settings_edit_form][settings][geojson_app_limit][bounds_limit_flag]"]' => ['checked' => TRUE]],
        ],
      ],
    ];

  }

  /**
   * Sets the GeojsonAppLimit Element data.
   *
   * @param array $geojson_app_limit
   *   Geojson_app_limit map settings.
   * @param array $settings
   *   Widget element settings.
   */
  public function setGeojsonAppLimitWidgetElementData(array &$geojson_app_limit, array $settings) {
    $geojson_app_limit = array_merge($geojson_app_limit, $settings["geojson_app_limit"] ?? []);
  }

}
