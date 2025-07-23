<?php

declare(strict_types=1);

namespace Drupal\leaflet_choropleth_custom\Plugin\Classification;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\leaflet_choropleth\Attribute\Classification;
use Drupal\leaflet_choropleth\Plugin\Classification\ClassificationBase;

/**
 * Provides a Five Breaks on 0-100 classification method.
 */
#[Classification(
  id: "5_breaks_on_0_100",
  label: new TranslatableMarkup("Five Breaks on 0-100"),
  description: new TranslatableMarkup("Creates 5 class breaks from 0 to 100."),
)]
class FiveBreaksOn0100 extends ClassificationBase {

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

    return [
      0,
      20,
      40,
      60,
      80,
      100,
    ];
  }

}
