<?php

namespace Drupal\geofield_311\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\geofield_311\Geofield311SettingsElementsTrait;
use Drupal\geofield_map\Plugin\views\style\GeofieldGoogleMapViewStyle;

/**
 * Style plugin to render a View output as a Leaflet map.
 *
 * @ingroup views_style_plugins
 *
 * Attributes set below end up in the $this->definition[] array.
 *
 * @ViewsStyle(
 *   id = "geofield_311_geofield_google_map",
 *   title = @Translation("Geofield 311 Geofield Google Map"),
 *   help = @Translation("Displays a View as a Geofield Google map, enhanced with Geofield 311."),
 *   display_types = {"normal"},
 *   theme = "leaflet-map"
 * )
 */
class Geofield311GeofieldGoogleMap extends GeofieldGoogleMapViewStyle {

  use Geofield311SettingsElementsTrait;

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $this->getGeofield311AdditionalGeojsonAppLimitSettings($form, $this->options);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $element = parent::render();
    array_unshift($element["#attached"]["library"], 'geofield_311/geofield_311');
    $this->setGeojsonAppLimitWidgetElementData($element["#attached"]["drupalSettings"]["geofield_google_map"][$element["#mapid"]]["geojson_app_limit"], $this->options);
    return $element;
  }

}
