<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Plugin\search_api\data_type;

/**
 * Provides a Typesense float[] data type.
 *
 * @SearchApiDataType(
 *   id = "typesense_float[]",
 *   label = @Translation("Typesense: float[]"),
 *   description = @Translation("Array of floating point / decimal numbers"),
 *   fallback_type = "decimal"
 * )
 */
class FloatMultiDataType extends FloatDataType {

}
