<?php

declare(strict_types=1);

namespace Drupal\leaflet_choropleth\Plugin\Classification;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\leaflet_choropleth\Attribute\Classification;

/**
 * Provides a 'Geometric Interval' classification method.
 */
#[Classification(
  id: "geometric_interval",
  label: new TranslatableMarkup("Geometric Interval"),
  description: new TranslatableMarkup("Creates class breaks based on geometric progression, ideal for data spanning multiple orders of magnitude."),
)]
class GeometricInterval extends ClassificationBase {

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

    $min = min($data);
    $max = max($data);

    // Handle edge case where min equals max.
    if ($min == $max) {
      return array_fill(0, $classes + 1, $min);
    }

    // Handle edge case where min is 0 or negative.
    // Geometric intervals require positive values.
    if ($min <= 0) {
      // Shift data to positive range.
      $offset = abs($min) + 1;
      $adjustedMin = $min + $offset;
      $adjustedMax = $max + $offset;

      // Calculate geometric ratio.
      $ratio = pow($adjustedMax / $adjustedMin, 1 / $classes);

      // Generate breaks in adjusted space.
      $breaks = [$adjustedMin];
      for ($i = 1; $i < $classes; $i++) {
        $breaks[] = $adjustedMin * pow($ratio, $i);
      }
      $breaks[] = $adjustedMax;

      // Shift back to original space.
      $breaks = array_map(function ($value) use ($offset) {
        return $value - $offset;
      }, $breaks);

      return $breaks;
    }

    // Standard geometric interval calculation for positive data.
    $ratio = pow($max / $min, 1 / $classes);

    $breaks = [$min];
    for ($i = 1; $i < $classes; $i++) {
      $breaks[] = $min * pow($ratio, $i);
    }
    $breaks[] = $max;

    return $breaks;
  }

}
