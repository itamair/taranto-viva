<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQLCompose\FieldType;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerItemInterface;
use Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerTrait;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeFieldType(
 *   id = "daterange",
 *   type_sdl = "DateRange",
 * )
 */
class DateRangeItem extends DateTimeItem implements FieldProducerItemInterface {

  use FieldProducerTrait;

  /**
   * {@inheritdoc}
   */
  public function resolveFieldItem(FieldItemInterface $item, FieldContext $context) {
    return [
      'start' => $item->value ? $this->toDateTimeType($item->start_date) : NULL,
      'end' => $item->end_value ? $this->toDateTimeType($item->end_date) : NULL,
    ];
  }

}
