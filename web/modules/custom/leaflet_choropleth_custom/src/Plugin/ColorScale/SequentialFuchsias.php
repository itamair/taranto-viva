<?php

declare(strict_types=1);

namespace Drupal\leaflet_choropleth_custom\Plugin\ColorScale;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\leaflet_choropleth\Attribute\ColorScale;
use Drupal\leaflet_choropleth\Plugin\ColorScale\ColorScaleBase;

/**
 * Provides a 'Fuchsias' sequential color scale.
 */
#[ColorScale(
  id: "sequential_fuchsia",
  label: new TranslatableMarkup("Fuchsias (Sequential)"),
  description: new TranslatableMarkup("Sequential color scale with fuchsia hues."),
)]
class SequentialFuchsias extends ColorScaleBase {

  /**
   * {@inheritdoc}
   */
  protected function getPalette(): array {
    return [
      3 => ['#e7e1ef', '#c994c7', '#e7298a'],
      4 => ['#e7e1ef', '#c994c7', '#e7298a', '#ce1256'],
      5 => ['#e7e1ef', '#c994c7', '#e7298a', '#ce1256', '#980043'],
      6 => ['#e7e1ef', '#c994c7', '#e7298a', '#ce1256', '#980043', '#ce1256'],
      7 => [
        '#f1eef6',
        '#d4b9da',
        '#c994c7',
        '#df65b0',
        '#e7298a',
        '#ce1256',
        '#980043',
      ],
      8 => [
        '#f7f4f9',
        '#f1eef6',
        '#d4b9da',
        '#c994c7',
        '#df65b0',
        '#e7298a',
        '#ce1256',
        '#980043',
      ],
      9 => [
        '#f7f4f9',
        '#f1eef6',
        '#d4b9da',
        '#c994c7',
        '#df65b0',
        '#e7298a',
        '#ce1256',
        '#980043',
        '#67001f',
      ],
    ];
  }

}
