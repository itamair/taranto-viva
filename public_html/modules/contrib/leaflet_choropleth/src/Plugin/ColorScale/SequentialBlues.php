<?php

declare(strict_types=1);

namespace Drupal\leaflet_choropleth\Plugin\ColorScale;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\leaflet_choropleth\Attribute\ColorScale;

/**
 * Provides a 'Blues' sequential color scale.
 */
#[ColorScale(
  id: "sequential_blues",
  label: new TranslatableMarkup("Blues (Sequential)"),
  description: new TranslatableMarkup("Sequential color scale with blue hues."),
)]
class SequentialBlues extends ColorScaleBase {

  /**
   * {@inheritdoc}
   */
  protected function getPalette(): array {
    return [
      3 => ['#deebf7', '#9ecae1', '#3182bd'],
      4 => ['#eff3ff', '#bdd7e7', '#6baed6', '#2171b5'],
      5 => ['#eff3ff', '#bdd7e7', '#6baed6', '#3182bd', '#08519c'],
      6 => ['#eff3ff', '#c6dbef', '#9ecae1', '#6baed6', '#3182bd', '#08519c'],
      7 => [
        '#eff3ff',
        '#c6dbef',
        '#9ecae1',
        '#6baed6',
        '#4292c6',
        '#2171b5',
        '#084594',
      ],
      8 => [
        '#f7fbff',
        '#deebf7',
        '#c6dbef',
        '#9ecae1',
        '#6baed6',
        '#4292c6',
        '#2171b5',
        '#084594',
      ],
      9 => [
        '#f7fbff',
        '#deebf7',
        '#c6dbef',
        '#9ecae1',
        '#6baed6',
        '#4292c6',
        '#2171b5',
        '#08519c',
        '#08306b',
      ],
    ];
  }

}
