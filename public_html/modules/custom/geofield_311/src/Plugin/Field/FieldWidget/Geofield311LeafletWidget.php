<?php

namespace Drupal\geofield_311\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geofield_311\Geofield311SettingsElementsTrait;
use Drupal\leaflet\Plugin\Field\FieldWidget\LeafletDefaultWidget;

/**
 * Plugin implementation of the "geofield_311_leaflet_widget" widget.
 *
 * @FieldWidget(
 *   id = "geofield_311_leaflet_widget",
 *   label = @Translation("Geofield 311 Leaflet Map"),
 *   description = @Translation("Provides a custom Geofield 311 Leaflet Widget, with Geoman Js Library and custom js addictions."),
 *   field_types = {
 *     "geofield",
 *   },
 * )
 */
class Geofield311LeafletWidget extends LeafletDefaultWidget {
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
    array_unshift($element['map']["#attached"]["library"], 'geofield_311/geofield_311');
    $this->setGeojsonAppLimitWidgetElementData($element["map"]["#attached"]["drupalSettings"]["leaflet"][$element["map"]["#map_id"]]["map"]["geojson_app_limit"], $settings);
    return $element;
  }

}
