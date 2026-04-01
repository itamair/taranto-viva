<?php

declare(strict_types=1);

namespace Drupal\leaflet_choropleth\Plugin\ColorScale;

/**
 * Interface for Leaflet choropleth color scale plugins.
 */
interface ColorScaleInterface {

  /**
   * Gets the color scale plugin ID.
   *
   * @return string
   *   The color scale plugin ID.
   */
  public function getId(): string;

  /**
   * Gets the color scale plugin label.
   *
   * @return string
   *   The color scale plugin label.
   */
  public function getLabel(): string;

  /**
   * Gets colors for the given number of classes.
   *
   * @param int $classes
   *   The number of color classes.
   * @param bool $reverse
   *   Whether to reverse the color scale.
   *
   * @return array
   *   Array of color hex codes.
   */
  public function getColors(int $classes, bool $reverse = FALSE): array;

}
