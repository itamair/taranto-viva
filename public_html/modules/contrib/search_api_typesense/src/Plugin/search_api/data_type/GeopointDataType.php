<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Plugin\search_api\data_type;

use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides a Typesense Geopoint data type.
 *
 * The value is expected in the format "latitude,longitude".
 *
 * @SearchApiDataType(
 *   id = "typesense_geopoint",
 *   label = @Translation("Typesense: Geopoint"),
 *   description = @Translation("A Geopoint"),
 *   fallback_type = "string"
 * )
 */
class GeopointDataType extends DataTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getValue($value) {
    if (\is_string($value) && \preg_match(
        '/^([0-9.-]+),([0-9.-]+)/i',
        $value,
        $matches,
      ) === 1) {
      $latitude = $matches[1];
      $longitude = $matches[2];

      return [(float) $latitude, (float) $longitude];
    }

    return $value;
  }

}
