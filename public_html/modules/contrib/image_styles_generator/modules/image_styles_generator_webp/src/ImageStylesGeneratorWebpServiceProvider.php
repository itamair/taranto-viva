<?php

namespace Drupal\image_styles_generator_webp;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Adds the WebP derivative warmer to the image styles generator service.
 */
class ImageStylesGeneratorWebpServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('image_styles_generator.derivative_warmer')) {
      $definition = $container->getDefinition('image_styles_generator.derivative_warmer');
      $definition->setClass('Drupal\image_styles_generator_webp\DerivativeWebpWarmer')
        ->addArgument(new Reference('webp.webp'));
    }
  }

}
