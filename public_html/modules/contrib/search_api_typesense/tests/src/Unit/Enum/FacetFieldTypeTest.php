<?php

declare(strict_types=1);

namespace Drupal\Tests\search_api_typesense\Unit\Enum;

use Drupal\search_api_typesense\Enum\FacetFieldType;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\search_api_typesense\Enum\FacetFieldType
 *
 * @group search_api_typesense
 */
class FacetFieldTypeTest extends UnitTestCase {

  /**
   * @covers ::types
   */
  public function testTypes(): void {
    self::assertEquals([
      'int32',
      'int64',
      'float',
      'int32[]',
      'int64[]',
      'float[]',
    ], FacetFieldType::Number->types());

    self::assertEquals([
      'string',
      'string[]',
    ], FacetFieldType::String->types());

    self::assertEquals([
      'bool',
      'bool[]',
    ], FacetFieldType::Bool->types());
  }

}
