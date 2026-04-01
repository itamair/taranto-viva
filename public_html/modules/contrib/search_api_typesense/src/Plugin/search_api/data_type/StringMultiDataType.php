<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Plugin\search_api\data_type;

/**
 * Provides a Typesense string[] data type.
 *
 * @SearchApiDataType(
 *   id = "typesense_string[]",
 *   label = @Translation("Typesense: string[]"),
 *   description = @Translation("Array of strings"),
 *   fallback_type = "string"
 * )
 */
class StringMultiDataType extends StringDataType {

}
