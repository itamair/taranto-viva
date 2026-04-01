<?php

declare(strict_types=1);

namespace Drupal\leaflet_choropleth\Plugin\ColorScale;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\leaflet_choropleth\Attribute\ColorScale;

/**
 * Provides a 'Grays' sequential color scale.
 */
#[ColorScale(
  id: "sequential_grays",
  label: new TranslatableMarkup("Grays (Sequential)"),
  description: new TranslatableMarkup("Sequential color scale with gray hues."),
)]
class SequentialGrays extends ColorScaleBase {

  /**
   * {@inheritdoc}
   */
  protected function getPalette(): array {
    return [
      3 => ['#f0f0f0', '#bdbdbd', '#636363'],
      4 => ['#f7f7f7', '#cccccc', '#969696', '#525252'],
      5 => ['#f7f7f7', '#cccccc', '#969696', '#636363', '#252525'],
      6 => ['#f7f7f7', '#d9d9d9', '#bdbdbd', '#969696', '#636363', '#252525'],
      7 => [
        '#f7f7f7',
        '#d9d9d9',
        '#bdbdbd',
        '#969696',
        '#737373',
        '#525252',
        '#252525',
      ],
      8 => [
        '#ffffff',
        '#f0f0f0',
        '#d9d9d9',
        '#bdbdbd',
        '#969696',
        '#737373',
        '#525252',
        '#252525',
      ],
      9 => [
        '#ffffff',
        '#f0f0f0',
        '#d9d9d9',
        '#bdbdbd',
        '#969696',
        '#737373',
        '#525252',
        '#252525',
        '#000000',
      ],
    ];
  }

}
