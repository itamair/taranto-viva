<?php

declare(strict_types=1);

namespace Drupal\Tests\search_api_typesense\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\search_api_typesense\Api\Config;
use Drupal\search_api_typesense\Api\SearchApiTypesenseException;
use Drupal\search_api_typesense\Api\TypesenseClient;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;

/**
 * Tests for the TypesenseClient class.
 *
 * @coversDefaultClass \Drupal\search_api_typesense\Api\TypesenseClient
 * @group search_api_typesense
 */
class PrepareItemValueTest extends UnitTestCase {

  /**
   * @var \Drupal\search_api_typesense\Api\TypesenseClient
   */
  private TypesenseClient $client;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();
    \Drupal::setContainer($container);

    $container->set('string_translation', $this->getStringTranslationStub());

    $http_client = $this->createMock(Client::class);
    $this->client = new TypesenseClient(
      new Config(
        api_key               : 'test',
        nearest_node          : NULL,
        nodes                 : [
          [
            'host' => '',
            'port' => '',
            'protocol' => '',
          ],
        ],
        retry_interval_seconds: 30,
        http_client           : $http_client,
      ),
    );
  }

  /**
   * Tests prepareItemValue method.
   *
   * @covers \Drupal\search_api_typesense\Api\TypesenseClient::prepareItemValue
   * @dataProvider valueProvider
   *
   * @phpstan-param array<int | bool | string> $value
   * @phpstan-param bool | float | int | string | array<string> | null $expected
   */
  public function testPrepareItemValue(
    array $value,
    string $type,
    string $field_name,
    bool | float | int | string | array | null $expected,
  ): void {
    if ($expected === NULL) {
      self::expectException(SearchApiTypesenseException::class);
    }

    $result = $this->client->prepareItemValue($value, $type, $field_name);
    self::assertEquals($expected, $result);
  }

  /**
   * Tests prepareId method.
   *
   * @covers \Drupal\search_api_typesense\Api\TypesenseClient::prepareId
   */
  public function testPrepareId(): void {
    $result = $this->client->prepareId("entity/5");

    self::assertEquals("entity-5", $result);
  }

  /**
   * @dataProvider valueProvider
   *
   * @phpstan-return array<int, list<bool|float|int|list<bool|int|\stdClass|string|null>|string|null>>
   */
  public static function valueProvider(): array {
    return [
      [[5], "typesense_string", "Field name one", "5"],
      [["5"], "typesense_string", "Field name two", "5"],
      [[TRUE], "typesense_string", "Field name three", "1"],
      [["5", "6"], "typesense_string[]", "Field name four", ["5", "6"]],
      [["5"], "typesense_int32", "Field name five", 5],
      [["5"], "typesense_float", "Field name six", 5.0],
      [["5"], "typesense_bool", "Field name seven", TRUE],
      [["5", "6"], "typesense_int32", "Field name eight", NULL],
      [[NULL], "typesense_int32", "Field name nine", NULL],
      [[new \stdClass()], "typesense_int32", "Field name ten", NULL],
    ];
  }

}
