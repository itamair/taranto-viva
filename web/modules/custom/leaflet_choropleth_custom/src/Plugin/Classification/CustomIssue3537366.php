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
  id: "custom_issue_3537366",
  label: new TranslatableMarkup("Custom Issue 3537366"),
  description: new TranslatableMarkup("Creates 5 class upon support request Issue #3537366."),
)]
class CustomIssue3537366 extends ClassificationBase {

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
      23,
      25,
      30,
      50,
      100,
    ];
  }

}
