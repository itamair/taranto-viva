<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQLCompose\FieldType;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerItemInterface;
use Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerTrait;
use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeFieldTypeBase;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeFieldType(
 *   id = "datetime",
 *   type_sdl = "DateTime",
 * )
 */
class DateTimeItem extends GraphQLComposeFieldTypeBase implements FieldProducerItemInterface {

  use FieldProducerTrait;

  /**
   * {@inheritdoc}
   */
  public function resolveFieldItem(FieldItemInterface $item, FieldContext $context) {
    $value = $item->date ?? $item->value ?? 'now';
    $value = $this->toDrupalDateTime($value);

    return $value ? $this->toDateTimeType($value) : NULL;
  }

  /**
   * Convert a mixed value to a DrupalDateTime object.
   *
   * @param mixed $value
   *   The value to convert.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime|null
   *   The converted DrupalDateTime object or NULL if conversion fails.
   */
  protected function toDrupalDateTime(mixed $value): ?DrupalDateTime {
    switch (TRUE) {
      case $value instanceof DrupalDateTime:
        return $value;

      case $value instanceof \DateTime:
        return DrupalDateTime::createFromDateTime($value);

      case is_numeric($value):
        return DrupalDateTime::createFromTimestamp($value, new \DateTimeZone('UTC'));

      case is_string($value):
        return new DrupalDateTime($value, new \DateTimeZone('UTC'));

      default:
        return NULL;
    }
  }

  /**
   * Convert a DrupalDateTime object to a date time type.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $value
   *   The DrupalDateTime object to convert.
   *
   * @return array
   *   The converted date time type as GraphQL expects.
   */
  protected function toDateTimeType(DrupalDateTime $value): array {
    return [
      'timestamp' => $value->getTimestamp(),
      'timezone' => $value->getTimezone()->getName(),
      'offset' => $value->format('P'),
      'time' => $value->format(\DateTime::RFC3339),
    ];
  }

}
