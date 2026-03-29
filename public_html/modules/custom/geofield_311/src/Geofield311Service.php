<?php

namespace Drupal\geofield_311;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Url;

/**
 * Provides a Geofield311Service class.
 */
class Geofield311Service {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Geofield311Service constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving required config objects.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory->get('geofield_311.settings');
  }

  /**
   * Returns the GeojsonAppLimit settings.
   *
   * @return array|\Drupal\Core\Config\ImmutableConfig|mixed|null
   *   The geojson_app_limit.
   */
  public function getGeojsonAppLimit() {
    $geojson_app_limit = $this->config->get('geojson_app_limit');
    // $drupal_root = DRUPAL_ROOT . '/';
    // $drupal_virtual_host = \Drupal::request()->getHost() . '/';
    $geojson_app_limit["file"] = Url::fromUri('base:', ['absolute' => TRUE])->toString() . $geojson_app_limit["file"];
    return $geojson_app_limit;
  }

}
