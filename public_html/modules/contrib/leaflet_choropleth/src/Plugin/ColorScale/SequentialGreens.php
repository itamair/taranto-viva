<?php

declare(strict_types=1);

namespace Drupal\leaflet_choropleth\Plugin\ColorScale;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\leaflet_choropleth\Attribute\ColorScale;

/**
 * Provides a 'Greens' sequential color scale.
 */
#[ColorScale(
  id: "sequential_greens",
  label: new TranslatableMarkup("Greens (Sequential)"),
  description: new TranslatableMarkup("Sequential color scale with green hues."),
)]
class SequentialGreens extends ColorScaleBase {

  /**
   * {@inheritdoc}
   */
  protected function getPalette(): array {
    return [
      3 => ['#e5f5e0', '#a1d99b', '#31a354'],
      4 => ['#edf8e9', '#bae4b3', '#74c476', '#238b45'],
      5 => ['#edf8e9', '#bae4b3', '#74c476', '#31a354', '#006d2c'],
      6 => ['#edf8e9', '#c7e9c0', '#a1d99b', '#74c476', '#31a354', '#006d2c'],
      7 => [
        '#edf8e9',
        '#c7e9c0',
        '#a1d99b',
        '#74c476',
        '#41ab5d',
        '#238b45',
        '#005a32',
      ],
      8 => [
        '#f7fcf5',
        '#e5f5e0',
        '#c7e9c0',
        '#a1d99b',
        '#74c476',
        '#41ab5d',
        '#238b45',
        '#005a32',
      ],
      9 => [
        '#f7fcf5',
        '#e5f5e0',
        '#c7e9c0',
        '#a1d99b',
        '#74c476',
        '#41ab5d',
        '#238b45',
        '#006d2c',
        '#00441b',
      ],
    ];
  }

}
