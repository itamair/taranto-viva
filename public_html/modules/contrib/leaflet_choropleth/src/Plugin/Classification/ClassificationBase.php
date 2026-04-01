<?php

declare(strict_types=1);

namespace Drupal\leaflet_choropleth\Plugin\Classification;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\leaflet_choropleth\ClassificationPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Leaflet choropleth classification plugins.
 */
abstract class ClassificationBase extends PluginBase implements ClassificationInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ClassificationPluginManager $classificationPluginManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): ClassificationInterface {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.leaflet_choropleth_classification'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return $this->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return $this->pluginDefinition['label'];
  }

}
