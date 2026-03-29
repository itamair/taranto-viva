<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQLCompose\FieldType;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerItemInterface;
use Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerTrait;
use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeFieldTypeBase;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeFieldType(
 *   id = "office_hours",
 *   type_sdl = "OfficeHours",
 * )
 */
class OfficeHoursItem extends GraphQLComposeFieldTypeBase implements FieldProducerItemInterface {

  use FieldProducerTrait;

  /**
   * {@inheritdoc}
   */
  public function resolveFieldItem(FieldItemInterface $item, FieldContext $context) {
    return [
      'day' => $item->day ?: 0,
      'dayDelta' => $item->day_delta ?: 0,
      'allDay' => $item->all_day ?: FALSE,
      'startHours' => $item->starthours ?: NULL,
      'endHours' => $item->endhours ?: NULL,
      'comment' => $item->comment ?: NULL,
    ];
  }

}
