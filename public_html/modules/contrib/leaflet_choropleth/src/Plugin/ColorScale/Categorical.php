<?php

declare(strict_types=1);

namespace Drupal\leaflet_choropleth\Plugin\ColorScale;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\leaflet_choropleth\Attribute\ColorScale;

/**
 * Provides a categorical color scale.
 */
#[ColorScale(
  id: "categorical",
  label: new TranslatableMarkup("Categorical"),
  description: new TranslatableMarkup("Distinct colors suitable for categorical data."),
)]
class Categorical extends ColorScaleBase {

  /**
   * {@inheritdoc}
   */
  protected function getPalette(): array {
    return [
      3 => ['#1f77b4', '#ff7f0e', '#2ca02c'],
      4 => ['#1f77b4', '#ff7f0e', '#2ca02c', '#d62728'],
      5 => ['#1f77b4', '#ff7f0e', '#2ca02c', '#d62728', '#9467bd'],
      6 => ['#1f77b4', '#ff7f0e', '#2ca02c', '#d62728', '#9467bd', '#8c564b'],
      7 => [
        '#1f77b4',
        '#ff7f0e',
        '#2ca02c',
        '#d62728',
        '#9467bd',
        '#8c564b',
        '#e377c2',
      ],
      8 => [
        '#1f77b4',
        '#ff7f0e',
        '#2ca02c',
        '#d62728',
        '#9467bd',
        '#8c564b',
        '#e377c2',
        '#7f7f7f',
      ],
      9 => [
        '#1f77b4',
        '#ff7f0e',
        '#2ca02c',
        '#d62728',
        '#9467bd',
        '#8c564b',
        '#e377c2',
        '#7f7f7f',
        '#bcbd22',
      ],
    ];
  }

}
