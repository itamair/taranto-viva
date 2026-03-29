<?php

declare(strict_types=1);

namespace Drupal\leaflet_choropleth\Plugin\Classification;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\leaflet_choropleth\Attribute\Classification;

/**
 * Provides a 'Jenks Natural Breaks' classification method.
 */
#[Classification(
  id: "jenks",
  label: new TranslatableMarkup("Natural Breaks (Jenks)"),
  description: new TranslatableMarkup("Identifies natural groupings inherent in the data."),
 )]
class Jenks extends ClassificationBase {

  /**
   * {@inheritdoc}
   */
  public function generateBreaks(array $data, int $classes): array {
    if (empty($data)) {
      return [0, 1];
    }

    // If we don't have enough data points, fall back to equal interval.
    if (count($data) <= $classes) {
      try {
        $equalInterval = $this->classificationPluginManager
          ->createInstance('equal_interval');
        return $equalInterval->generateBreaks($data, $classes);
      }
      catch (\Exception $e) {
        return [];
      }
    }

    // Sort the data.
    sort($data);

    // Calculate differences between adjacent values.
    $differences = [];
    for ($i = 1; $i < count($data); $i++) {
      $differences[$i - 1] = [
        'index' => $i,
        'value' => $data[$i] - $data[$i - 1],
      ];
    }

    // Sort differences in descending order.
    usort($differences, function ($a, $b) {
      return $b['value'] <=> $a['value'];
    });

    // Take the top n-1 breakpoints.
    $breakpoints = array_slice($differences, 0, $classes - 1);

    // Sort breakpoints by index.
    usort($breakpoints, function ($a, $b) {
      return $a['index'] <=> $b['index'];
    });

    // Construct breaks.
    $breaks = [$data[0]];
    foreach ($breakpoints as $point) {
      $breaks[] = $data[$point['index']];
    }
    $breaks[] = $data[count($data) - 1];

    return $breaks;
  }

}
