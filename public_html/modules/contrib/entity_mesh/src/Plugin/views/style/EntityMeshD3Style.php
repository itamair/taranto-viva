<?php

namespace Drupal\entity_mesh\Plugin\views\style;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\entity_mesh\ProcessDataForD3Trait;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a custom D3.js style for Views.
 *
 * @ViewsStyle(
 *   id = "entity_mesh_d3_style",
 *   title = @Translation("Entity Mesh D3 visualization"),
 *   help = @Translation("Render the View using D3.js."),
 *   theme = "views_view_entity_mesh_d3",
 *   display_types = {"normal"}
 * )
 */
final class EntityMeshD3Style extends StylePluginBase implements ContainerFactoryPluginInterface {

  use ProcessDataForD3Trait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin_object = new static($configuration, $plugin_id, $plugin_definition);
    $plugin_object->entityTypeManager = $container->get('entity_type.manager');
    return $plugin_object;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    // Add custom options if needed.
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $display = $this->view->getDisplay();
    $build = [
      '#theme' => $this->themeFunctions(),
      '#view' => $this->view,
      '#rows' => $this->processData($this->view->result, 'entity_mesh_'),
      '#attached' => [
        'library' => [
          'entity_mesh/d3',
          'entity_mesh/entity_mesh',
        ],
      ],
    ];
    $build['#attached']['drupalSettings']['entity_mesh']['settings'] = ['fullHeight' => $display->pluginId === 'page'];
    return $build;
  }

}
