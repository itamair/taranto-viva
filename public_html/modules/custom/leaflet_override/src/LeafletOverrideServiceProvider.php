<?php

namespace Drupal\leaflet_override;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Alters the custom_module services.
 */
class LeafletOverrideServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    // Check if the service exists before overriding.
    if ($container->hasDefinition('leaflet.service')) {
      // Override the service definition.
      $definition = $container->getDefinition('leaflet.service');
      // Set the class to a new class in this module.
      // $definition->setClass('Drupal\leaflet_override\LeafletServiceOverride')->addArgument(new Reference('cache.leaflet_permanent'));
    }
  }

}
