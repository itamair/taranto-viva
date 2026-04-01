<?php

declare(strict_types=1);

namespace Drupal\leaflet_choropleth\Plugin\Classification;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\leaflet_choropleth\Attribute\Classification;

/**
 * Provides a 'Quantile' classification method.
 */
#[Classification(
  id: "quantile",
  label: new TranslatableMarkup("Quantile (Equal Count)"),
  description: new TranslatableMarkup("Places an equal number of observations in each class."),
)]
class Quantile extends ClassificationBase {

  /**
   * {@inheritdoc}
   */
  public function generateBreaks(array $data, int $classes): array {
    if (empty($data)) {
      return [0, 1];
    }

    sort($data);
    $count = count($data);

    $breaks = [$data[0]];

    for ($i = 1; $i < $classes; $i++) {
      $index = (int) floor($i * $count / $classes);
      $breaks[] = $data[$index];
    }

    $breaks[] = $data[$count - 1];

    return $breaks;
  }

}
