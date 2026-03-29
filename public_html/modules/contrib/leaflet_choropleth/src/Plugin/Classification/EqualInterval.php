<?php

declare(strict_types=1);

namespace Drupal\leaflet_choropleth\Plugin\Classification;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\leaflet_choropleth\Attribute\Classification;

/**
 * Provides an 'Equal Interval' classification method.
 */
#[Classification(
  id: "equal_interval",
  label: new TranslatableMarkup("Equal Interval"),
  description: new TranslatableMarkup("Divides data into equal-sized ranges."),
)]
class EqualInterval extends ClassificationBase {

  /**
   * {@inheritdoc}
   */
  public function generateBreaks(array $data, int $classes): array {
    if (empty($data)) {
      return [0, 1];
    }

    $min = min($data);
    $max = max($data);
    $range = $max - $min;
    $interval = $range / $classes;

    $breaks = [$min];
    for ($i = 1; $i <= $classes; $i++) {
      $breaks[] = $min + ($interval * $i);
    }

    return $breaks;
  }

}
