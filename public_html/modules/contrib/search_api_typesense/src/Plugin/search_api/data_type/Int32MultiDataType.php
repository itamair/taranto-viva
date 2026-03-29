<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Plugin\search_api\data_type;

/**
 * Provides a Typesense int32[] data type.
 *
 * @SearchApiDataType(
 *   id = "typesense_int32[]",
 *   label = @Translation("Typesense: int32[]"),
 *   description = @Translation("Array of int32"),
 *   fallback_type = "integer"
 * )
 */
class Int32MultiDataType extends Int32DataType {

}
