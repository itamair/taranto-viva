<?php

declare(strict_types=1);

namespace Drupal\leaflet_choropleth\Plugin\ColorScale;

use Drupal\Core\Plugin\PluginBase;

/**
 * Base class for Leaflet choropleth color scale plugins.
 */
abstract class ColorScaleBase extends PluginBase implements ColorScaleInterface {

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return $this->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getColors(int $classes, bool $reverse = FALSE): array {
    // Clamp classes to available range.
    $classes = max(3, min(9, $classes));

    // Get colors from the palette.
    $colors = $this->getPalette()[$classes];

    // Reverse colors if needed.
    if ($reverse) {
      $colors = array_reverse($colors);
    }

    return $colors;
  }

  /**
   * Gets the palette for this color scale.
   *
   * This should return an array with keys representing number of classes,
   * and values being arrays of color hex codes.
   *
   * @return array
   *   Array of color palettes for different class counts.
   */
  abstract protected function getPalette(): array;

}
