<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Plugin\search_api\data_type;

/**
 * Provides a Typesense bool[] data type.
 *
 * @SearchApiDataType(
 *   id = "typesense_bool[]",
 *   label = @Translation("Typesense: bool[]"),
 *   description = @Translation("Array of booleans"),
 *   fallback_type = "boolean"
 * )
 */
class BoolMultiDataType extends BoolDataType {

}
