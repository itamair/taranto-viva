<?php

namespace Drupal\geofield_311\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\geofield_311\Geofield311SettingsElementsTrait;
use Drupal\geofield_map\Element\GeofieldMap;

/**
 * Provides a Geofield Map form element.
 *
 * @FormElement("geofield_311_geofield_map")
 */
class Geofield311GeofieldMap extends GeofieldMap {

  use Geofield311SettingsElementsTrait;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#process' => [
        [$class, 'geofield311LatLonProcess'],
      ],
      '#element_validate' => [
        [$class, 'elementValidate'],
      ],
      '#theme_wrappers' => ['fieldset'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function geofield311LatLonProcess(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $element = self::latLonProcess($element, $form_state, $complete_form);
    array_unshift($element["#attached"]["library"], 'geofield_311/geofield_311');
    $element["#attached"]["drupalSettings"]["geofield_map"][$element["#mapid"]]["geojson_app_limit"] = array_merge(\Drupal::service('geofield_311.service')->getGeojsonAppLimit(), $element["#geojson_app_limit"]);
    return $element;
  }

}
