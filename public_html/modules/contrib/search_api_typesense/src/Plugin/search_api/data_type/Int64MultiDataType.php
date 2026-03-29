<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Plugin\search_api\data_type;

/**
 * Provides a Typesense int64[] data type.
 *
 * @SearchApiDataType(
 *   id = "typesense_int64[]",
 *   label = @Translation("Typesense: int64[]"),
 *   description = @Translation("Array of int64"),
 *   fallback_type = "integer"
 * )
 */
class Int64MultiDataType extends Int64DataType {

}
