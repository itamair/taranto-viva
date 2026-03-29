<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Enum;

/**
 * Enum for Typesense facet field types.
 */
enum FacetFieldType: string {
  case Number = 'number';
  case String = 'string';
  case Bool = 'bool';

  /**
   * Returns the Typesense field type for the enum value.
   *
   * This method returns an array of Typesense field types that correspond to
   * the kind of facet refinement you need to display in the UI.
   *
   * @return array<int, string>
   *   The Typesense field type.
   */
  public function types(): array {
    return match($this) {
      self::Number => [
        'int32',
        'int64',
        'float',
        'int32[]',
        'int64[]',
        'float[]',
      ],
      self::String => [
        'string',
        'string[]',
      ],
      self::Bool => [
        'bool',
        'bool[]',
      ],
    };
  }

}
