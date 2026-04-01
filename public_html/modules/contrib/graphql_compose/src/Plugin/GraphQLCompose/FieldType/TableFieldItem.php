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
 *   id = "tablefield",
 *   type_sdl = "Table",
 * )
 */
class TableFieldItem extends GraphQLComposeFieldTypeBase implements FieldProducerItemInterface {

  use FieldProducerTrait;

  /**
   * {@inheritdoc}
   */
  public function resolveFieldItem(FieldItemInterface $item, FieldContext $context) {

    // Strip caption from value.
    $values = array_filter($item->value, is_numeric(...), ARRAY_FILTER_USE_KEY);

    $rows = [];

    // Separate weight and data.
    foreach ($values as $value) {
      $rows[] = [
        'weight' => $value['weight'] ?? 0,
        'data' => array_filter($value, is_numeric(...), ARRAY_FILTER_USE_KEY),
      ];
    }

    return [
      'caption' => $item->caption ?: NULL,
      'rows' => $rows,
      'format' => $item->format ?? NULL,
    ];
  }

}
