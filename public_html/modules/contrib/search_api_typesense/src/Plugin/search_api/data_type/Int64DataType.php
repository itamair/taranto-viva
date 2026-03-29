<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Plugin\search_api\data_type;

use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides a Typesense int64 data type.
 *
 * @SearchApiDataType(
 *   id = "typesense_int64",
 *   label = @Translation("Typesense: int64"),
 *   description = @Translation("Integer values larger than 2,147,483,647"),
 *   fallback_type = "integer"
 * )
 */
class Int64DataType extends DataTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getValue($value): int {
    return (int) $value;
  }

}
