<?php

declare(strict_types=1);

namespace Drupal\leaflet_choropleth\Plugin\ColorScale;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\leaflet_choropleth\Attribute\ColorScale;

/**
 * Provides a 'Red-Blue' Diverging color scale.
 */
#[ColorScale(
  id: "diverging_redblue",
  label: new TranslatableMarkup("Red-Blue (Diverging)"),
  description: new TranslatableMarkup("Diverging color scale with red and blue endpoints."),
)]
class DivergingRedBlue extends ColorScaleBase {

  /**
   * {@inheritdoc}
   */
  protected function getPalette(): array {
    return [
      3 => ['#ef8a62', '#f7f7f7', '#67a9cf'],
      4 => ['#ca0020', '#f4a582', '#92c5de', '#0571b0'],
      5 => ['#ca0020', '#f4a582', '#f7f7f7', '#92c5de', '#0571b0'],
      6 => ['#b2182b', '#ef8a62', '#fddbc7', '#d1e5f0', '#67a9cf', '#2166ac'],
      7 => [
        '#b2182b',
        '#ef8a62',
        '#fddbc7',
        '#f7f7f7',
        '#d1e5f0',
        '#67a9cf',
        '#2166ac',
      ],
      8 => [
        '#b2182b',
        '#d6604d',
        '#f4a582',
        '#fddbc7',
        '#d1e5f0',
        '#92c5de',
        '#4393c3',
        '#2166ac',
      ],
      9 => [
        '#b2182b',
        '#d6604d',
        '#f4a582',
        '#fddbc7',
        '#f7f7f7',
        '#d1e5f0',
        '#92c5de',
        '#4393c3',
        '#2166ac',
      ],
    ];
  }

}
