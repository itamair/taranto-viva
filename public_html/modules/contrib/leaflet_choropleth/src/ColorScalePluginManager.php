<?php

declare(strict_types=1);

namespace Drupal\leaflet_choropleth;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\leaflet_choropleth\Attribute\ColorScale;

/**
 * Plugin manager for Leaflet Choropleth Color Scale plugins.
 */
class ColorScalePluginManager extends DefaultPluginManager {

  /**
   * Constructs a ColorScalePluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/ColorScale',
      $namespaces,
      $module_handler,
      'Drupal\leaflet_choropleth\Plugin\ColorScale\ColorScaleInterface',
      ColorScale::class
    );
    $this->alterInfo('leaflet_choropleth_color_scale_info');
    $this->setCacheBackend($cache_backend, 'leaflet_choropleth_color_scale_plugins');
  }

  /**
   * Gets a list of available color scales as options for form elements.
   *
   * @return array
   *   Array of color scale options, keyed by plugin ID.
   */
  public function getOptions(): array {
    $options = [];

    foreach ($this->getDefinitions() as $plugin_id => $definition) {
      $options[$plugin_id] = $definition['label'];
    }

    return $options;
  }

}
