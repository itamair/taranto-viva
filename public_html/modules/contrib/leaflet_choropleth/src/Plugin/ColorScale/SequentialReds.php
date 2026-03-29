<?php

declare(strict_types=1);

namespace Drupal\leaflet_choropleth\Plugin\ColorScale;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\leaflet_choropleth\Attribute\ColorScale;

/**
 * Provides a 'Reds' sequential color scale.
 */
#[ColorScale(
  id: "sequential_reds",
  label: new TranslatableMarkup("Reds (Sequential)"),
  description: new TranslatableMarkup("Sequential color scale with red hues."),
)]
class SequentialReds extends ColorScaleBase {

  /**
   * {@inheritdoc}
   */
  protected function getPalette(): array {
    return [
      3 => ['#fee0d2', '#fb6a4a', '#de2d26'],
      4 => ['#fee5d9', '#fcae91', '#fb6a4a', '#cb181d'],
      5 => ['#fee5d9', '#fcae91', '#fb6a4a', '#de2d26', '#a50f15'],
      6 => ['#fee5d9', '#fcbba1', '#fc9272', '#fb6a4a', '#de2d26', '#a50f15'],
      7 => [
        '#fee5d9',
        '#fcbba1',
        '#fc9272',
        '#fb6a4a',
        '#ef3b2c',
        '#cb181d',
        '#99000d',
      ],
      8 => [
        '#fff5f0',
        '#fee0d2',
        '#fcbba1',
        '#fc9272',
        '#fb6a4a',
        '#ef3b2c',
        '#cb181d',
        '#99000d',
      ],
      9 => [
        '#fff5f0',
        '#fee0d2',
        '#fcbba1',
        '#fc9272',
        '#fb6a4a',
        '#ef3b2c',
        '#cb181d',
        '#a50f15',
        '#67000d',
      ],
    ];
  }

}
