<?php

declare(strict_types=1);

namespace Drupal\leaflet_choropleth\Service;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\leaflet\LeafletService;
use Drupal\leaflet_choropleth\ColorScalePluginManager;
use Drupal\leaflet_choropleth\ClassificationPluginManager;

/**
 * Service for processing choropleth map data.
 */
class LeafletChoroplethProcessor {

  use StringTranslationTrait;
  use LoggerChannelTrait;
  use MessengerTrait;

  /**
   * Constructor.
   *
   * @param \Drupal\leaflet\LeafletService $leafletService
   *   The Leaflet service.
   * @param \Drupal\leaflet_choropleth\ColorScalePluginManager $colorScaleManager
   *   The color scale plugin manager.
   * @param \Drupal\leaflet_choropleth\ClassificationPluginManager $classificationManager
   *   The classification plugin manager.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   */
  public function __construct(
    protected readonly LeafletService $leafletService,
    protected readonly ColorScalePluginManager $colorScaleManager,
    protected readonly ClassificationPluginManager $classificationManager,
    protected readonly AccountInterface $currentUser,
  ) {
  }

  /**
   * Processes features for choropleth maps.
   *
   * @param array $feature
   *   The feature to process.
   * @param mixed $data_value
   *   The data value for this feature.
   * @param array $choropleth_settings
   *   The choropleth settings.
   * @param array $color_scale
   *   The color scale to use.
   */
  public function processChoroplethFeature(array &$feature, mixed $data_value, array $choropleth_settings, array $color_scale): void {
    if ($feature['type'] === 'polygon' || $feature['type'] === 'multipolygon' && is_numeric($data_value)) {
      // Store the data value for use in JavaScript.
      $feature['choropleth'] = [
        'value' => $data_value,
      ];

      // Set fill color based on data value and color scale.
      $color_index = $this->getColorIndexForValue($data_value, $color_scale);

      if ($color_index !== -1) {
        $feature['path'] = Json::decode($feature['path']) ?? [];

        $choropleth_setting_path = Json::decode($choropleth_settings["path"]) ?? [];

        // Set path properties based on choropleth settings path.
        foreach ($choropleth_setting_path as $k => $choropleth_setting) {
          $feature['path'][$k] = $choropleth_setting ?? '';
        }

        // Finally override the fillColor property with the chosen choropleth
        // color scale value.
        $feature['path']['fillColor'] = $color_scale['legend_items'][$color_index]['color'] ?? '';
      }
    }
  }

  /**
   * Gets the color index for a given value.
   *
   * @param mixed $value
   *   The value to get the color index for.
   * @param array $color_scale
   *   The color scale to use.
   *
   * @return int
   *   The color index.
   */
  protected function getColorIndexForValue(mixed $value, array $color_scale): int {
    if (isset($color_scale['legend_items']) && $legend_items = $color_scale['legend_items']) {
      $num_classes = count($legend_items);
      if ($num_classes == 0) {
        return -1;
      }
      else {
        for ($i = 0; $i < $num_classes; $i++) {
          if ($value <= $legend_items[$i]['values']['max']) {
            return $i;
          }
        }
        return $num_classes - 1;
      }
    }
    else {
      return -1;
    }
  }

  /**
   * Generates a color scale for choropleth maps.
   *
   * @param array $data_values
   *   The data values to classify.
   * @param array $settings
   *   The choropleth settings.
   *
   * @return array
   *   The color scale information.
   */
  public function generateColorScale(array $data_values, array $settings): array {
    $classes = (int) $settings['classes'] ?? 5;
    $color_scale_id = $settings['color_scale'] ?? 'sequential_blues';
    $classification_id = $settings['classification'] ?? 'quantile';
    $reverse = !empty($settings['reverse']);

    // Filter out null/empty values.
    $data_values = array_filter($data_values, function ($value) {
      return $value !== NULL && $value !== '';
    });

    // If no valid data values, return empty scale.
    if (empty($data_values)) {
      return [];
    }

    // Get min/max values.
    $min = min($data_values);
    $max = max($data_values);

    $count = count($data_values);
    $classes = min($count, $classes);

    try {
      // Create classification plugin instance and generate breaks.
      $classification = $this->classificationManager->createInstance($classification_id);
      $breaks = $classification->generateBreaks($data_values, $classes);

      // Create color scale plugin instance and get colors.
      $color_scale_plugin = $this->colorScaleManager->createInstance($color_scale_id);
      $colors = $color_scale_plugin->getColors($classes, $reverse);
    }
    catch (PluginException $e) {
      $message = $this->t("Leaflet Choropleth map couldn't be processed / generated. @exception_message", [
        '@exception_message' => $e->getMessage(),
      ]);
      $this->getLogger('Leaflet Choropleth')->warning($message);
      if (!$this->currentUser->isAnonymous()) {
        $this->messenger()->addWarning($message);
      }
      return [];
    }
    $legend_items = [];
    foreach ($colors as $i => $color) {
      $legend_items[$i] = [
        'color' => $color,
        'values' => [
          'min' => $breaks[$i],
          'max' => $breaks[$i + 1],
          'value' => $settings["legend"]["values"][$i + 1] ?? '',
        ],
      ];
    }

    return [
      'min' => $min,
      'max' => $max,
      'legend_items' => $legend_items,
      'method' => $classification_id,
    ];
  }

}
