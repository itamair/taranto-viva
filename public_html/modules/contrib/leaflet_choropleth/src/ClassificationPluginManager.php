<?php

declare(strict_types=1);

namespace Drupal\leaflet_choropleth;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\leaflet_choropleth\Attribute\Classification;

/**
 * Plugin manager for Leaflet Choropleth Classification plugins.
 */
class ClassificationPluginManager extends DefaultPluginManager {

  /**
   * Constructs a ClassificationPluginManager object.
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
      'Plugin/Classification',
      $namespaces,
      $module_handler,
      'Drupal\leaflet_choropleth\Plugin\Classification\ClassificationInterface',
      Classification::class
    );
    $this->alterInfo('leaflet_choropleth_classification_info');
    $this->setCacheBackend($cache_backend, 'leaflet_choropleth_classification_plugins');
  }

  /**
   * Gets a list of available classifications as options for form elements.
   *
   * @return array
   *   Array of classification options, keyed by labels and descriptions and
   *   plugin IDs.
   */
  public function getOptions(): array {
    $options = [];

    foreach ($this->getDefinitions() as $plugin_id => $definition) {
      $options['labels'][$plugin_id] = $definition['label'];
      $options['descriptions'][$plugin_id] = $definition['description'];
    }
    return $options;
  }

}
