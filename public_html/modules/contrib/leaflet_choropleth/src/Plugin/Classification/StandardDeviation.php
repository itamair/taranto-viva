<?php

declare(strict_types=1);

namespace Drupal\leaflet_choropleth\Plugin\Classification;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\leaflet_choropleth\Attribute\Classification;

/**
 * Provides a 'Standard Deviation' classification method.
 */
#[Classification(
  id: "standard_deviation",
  label: new TranslatableMarkup("Standard Deviation"),
  description: new TranslatableMarkup("Creates classes based on how far values deviate from the dataset's mean, using Standard Deviation as the interval measure."),
)]
class StandardDeviation extends ClassificationBase {

  /**
   * {@inheritdoc}
   */
  public function generateBreaks(array $data, int $classes): array {
    if (empty($data)) {
      return [0, 1];
    }

    // Calculate mean.
    $mean = array_sum($data) / count($data);

    // Calculate standard deviation.
    $variance = 0;
    foreach ($data as $value) {
      $variance += pow($value - $mean, 2);
    }
    $stdDev = sqrt($variance / count($data));

    // Handle edge case where standard deviation is 0
    // ( fall back to equal interval).
    if ($stdDev == 0) {
      try {
        $equalInterval = $this->classificationPluginManager
          ->createInstance('equal_interval');
        return $equalInterval->generateBreaks($data, $classes);
      }
      catch (\Exception $e) {
        return [];
      }
    }

    // Calculate breaks based on standard deviation.
    $breaks = [];
    $min = min($data);
    $max = max($data);

    // For odd number of classes, center class around mean.
    if ($classes % 2 == 1) {
      $centerIndex = intval($classes / 2);

      // Create breaks symmetrically around mean.
      for ($i = 0; $i <= $classes; $i++) {
        $deviations = ($i - $centerIndex) * 0.5;
        $break = $mean + ($deviations * $stdDev);
        $breaks[] = $break;
      }
    }
    else {
      // For even number of classes, create breaks symmetrically.
      $halfClasses = $classes / 2;

      for ($i = 0; $i <= $classes; $i++) {
        $deviations = ($i - $halfClasses) * 0.5;
        $break = $mean + ($deviations * $stdDev);
        $breaks[] = $break;
      }
    }

    // Ensure breaks don't exceed data bounds.
    $breaks[0] = $min;
    $breaks[count($breaks) - 1] = $max;

    // Sort breaks and ensure they are within data range.
    sort($breaks);

    // Remove duplicates and ensure proper ordering.
    $breaks = array_unique($breaks);
    $breaks = array_values($breaks);

    return $breaks;
  }

}
