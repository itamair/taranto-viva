<?php

declare(strict_types=1);

namespace Drupal\leaflet_choropleth\Plugin\Classification;

/**
 * Interface for Leaflet choropleth classification plugins.
 */
interface ClassificationInterface {

  /**
   * Gets the classification plugin ID.
   *
   * @return string
   *   The classification plugin ID.
   */
  public function getId(): string;

  /**
   * Gets the classification plugin label.
   *
   * @return string
   *   The classification plugin label.
   */
  public function getLabel(): string;

  /**
   * Generates breaks for the given data array and number of classes.
   *
   * @param array $data
   *   Array of numerical data to classify.
   * @param int $classes
   *   Number of classes to divide data into.
   *
   * @return array
   *   Array of break points defining class boundaries.
   */
  public function generateBreaks(array $data, int $classes): array;

}
