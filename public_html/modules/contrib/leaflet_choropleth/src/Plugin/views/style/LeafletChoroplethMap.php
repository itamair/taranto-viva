<?php

declare(strict_types=1);

namespace Drupal\leaflet_choropleth\Plugin\views\style;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\leaflet_views\Plugin\views\style\LeafletMap;
use Drupal\leaflet_choropleth\LeafletChoroplethSettingsTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Style plugin for the leaflet choropleth map.
 *
 * @ViewsStyle(
 *   id = "leaflet_choropleth_map",
 *   title = @Translation("Leaflet Choropleth Map"),
 *   help = @Translation("Displays a View as a Leaflet choropleth map."),
 *   display_types = {"normal"},
 *   theme = "leaflet-map"
 * )
 */
class LeafletChoroplethMap extends LeafletMap implements ContainerFactoryPluginInterface {

  use LeafletChoroplethSettingsTrait;

  /**
   * Constructs a LeafletChoroplethMap style instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected $entityManager,
    protected $entityFieldManager,
    protected $entityDisplayRepository,
    protected $currentUser,
    protected $messenger,
    protected $renderer,
    protected $moduleHandler,
    protected $leafletService,
    protected $link,
    protected $fieldTypeManager,
    protected $leafletChoroplethProcessor,
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $this->entityManager,
      $this->entityFieldManager,
      $this->entityDisplayRepository,
      $this->currentUser,
      $this->messenger,
      $this->renderer,
      $this->moduleHandler,
      $this->leafletService,
      $this->link,
      $this->fieldTypeManager,
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_display.repository'),
      $container->get('current_user'),
      $container->get('messenger'),
      $container->get('renderer'),
      $container->get('module_handler'),
      $container->get('leaflet.service'),
      $container->get('link_generator'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('leaflet_choropleth.processor'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    // Add default choropleth options.
    $options['choropleth'] = ['default' => $this->getDefaultChoroplethSettings()];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    // Build the parent form first.
    parent::buildOptionsForm($form, $form_state);

    // Add the choropleth settings.
    $this->setChoroplethMapSettings($form, $form_state, $this->options['choropleth'], $this->viewFields);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    // Render the map using the parent method.
    $element = parent::render();

    // If choropleth is enabled, add the choropleth library.
    if (!empty($this->options['choropleth']['enabled'])) {
      $element['#attached']['library'][] = 'leaflet_choropleth/leaflet_choropleth';

      // Merge choropleth settings to be passed to the map.
      if (isset($element['#attached']['drupalSettings']['leaflet'])) {
        foreach ($element['#attached']['drupalSettings']['leaflet'] as $map_id => $settings) {
          $element['#attached']['drupalSettings']['leaflet'][$map_id]['map']['settings']['choropleth'] = isset($element["#map"]["settings"]["choropleth"]) ? array_merge($element["#map"]["settings"]["choropleth"], $this->options['choropleth']) : $this->options['choropleth'];
        }
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function processEntityFeatures(
    array &$features,
    array $entity_details,
    $result,
    array &$map,
    string $geofield_name,
    int $id,
    string $group_label,
    array $view_results_groups,
  ) {
    // Call the parent method to process features.
    parent::processEntityFeatures(
      $features,
      $entity_details,
      $result,
      $map,
      $geofield_name,
      $id,
      $group_label,
      $view_results_groups
    );

    // Add choropleth processing if enabled.
    if (!empty($this->options['choropleth']['enabled']) && !empty($this->options['choropleth']['data_field'])) {
      $data_field = $this->options['choropleth']['data_field'];
      $data_value = $this->getFieldValue($id, $data_field);

      // Convert to numeric value.
      if (is_array($data_value) && !empty($data_value)) {
        $data_value = reset($data_value);
      }
      if (is_string($data_value)) {
        $data_value = is_numeric($data_value) ? (float) $data_value : NULL;
      }

      // Only process if we have a numeric value.
      if (is_numeric($data_value)) {
        // Get all data values for the color scale.
        $all_data_values = [];
        foreach ($view_results_groups as $view_results_group) {
          foreach ($view_results_group['rows'] as $row_id => $row_result) {
            $row_value = $this->getFieldValue($row_id, $data_field);
            if (is_array($row_value) && !empty($row_value)) {
              $row_value = reset($row_value);
            }
            if (is_string($row_value) && is_numeric($row_value)) {
              $row_value = (float) $row_value;
            }
            if (is_numeric($row_value)) {
              $all_data_values[] = $row_value;
            }
          }
        }

        // Generate color scale.
        $color_scale = $this->leafletChoroplethProcessor->generateColorScale($all_data_values, $this->options['choropleth']);

        // Allow modules to adjust the color scale, on this view style.
        $this->moduleHandler->alter('leaflet_choropleth_color_scale', $color_scale, $this);

        // Process features for choropleth display.
        foreach ($features as &$feature) {
          if ($feature['type'] === 'polygon' || $feature['type'] === 'multipolygon') {
            $this->leafletChoroplethProcessor->processChoroplethFeature(
              $feature,
              $data_value,
              $this->options['choropleth'],
              $color_scale
            );
          }
        }

        // Store color scale in map settings.
        $map['settings']['choropleth']['colorScale'] = $color_scale;
      }
    }
  }

  /**
   * Callback for the select element.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array|AjaxResponse
   *   The form structure.
   */
  public static function promptCallback(array $form, FormStateInterface $form_state): array|AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand("#choropleth-legend-values-wrapper", $form['options']['style_options']['choropleth']['legend']['values']));
    return $response;
  }

}
