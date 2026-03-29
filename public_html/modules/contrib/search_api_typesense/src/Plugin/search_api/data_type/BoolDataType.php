<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Plugin\search_api\data_type;

use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides a Typesense bool data type.
 *
 * @SearchApiDataType(
 *   id = "typesense_bool",
 *   label = @Translation("Typesense: bool"),
 *   description = @Translation("true or false"),
 *   fallback_type = "boolean"
 * )
 */
class BoolDataType extends DataTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getValue($value): bool {
    return (boolean) $value;
  }

}
