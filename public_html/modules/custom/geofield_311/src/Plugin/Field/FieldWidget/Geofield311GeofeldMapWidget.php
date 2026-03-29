<?php

namespace Drupal\geofield_311\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geofield_311\Geofield311SettingsElementsTrait;
use Drupal\geofield_map\Plugin\Field\FieldWidget\GeofieldMapWidget;

/**
 * Plugin implementation of the "geofield_311_leaflet_widget" widget.
 *
 * @FieldWidget(
 *   id = "geofield_311_geofield_map_widget",
 *   label = @Translation("Geofield 311 Geofield Map"),
 *   description = @Translation("Provides a custom Geofield 311 Geofield Map Widget, with Geoman Js Library and custom js addictions."),
 *   field_types = {
 *     "geofield",
 *   },
 * )
 */
class Geofield311GeofeldMapWidget extends GeofieldMapWidget {
  use Geofield311SettingsElementsTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = parent::defaultSettings();
    $geofield31_additional_default_settings = self::getGeofield311AdditionalDefaultSettings();
    $settings['geojson_app_limit'] = $geofield31_additional_default_settings['geojson_app_limit'];
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    $this->getGeofield311AdditionalGeojsonAppLimitSettings($form, $this->getSettings());
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(
    FieldItemListInterface $items,
    $delta,
    array $element,
    array &$form,
    FormStateInterface $form_state
  ) {
    $settings = $this->getSettings();
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    // Add the geojson_app_limit settings to the element value.
    $element["value"]["#geojson_app_limit"] = $settings["geojson_app_limit"];
    // Change the Element type to render the Geofield Map Widget to the custom
    // 'geofield_311_geofield_map'.
    $element["value"]["#type"] = 'geofield_311_geofield_map';
    return $element;
  }

}
