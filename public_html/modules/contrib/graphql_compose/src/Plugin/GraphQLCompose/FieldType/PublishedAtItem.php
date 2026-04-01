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
 *   id = "published_at",
 *   type_sdl = "DateTime",
 * )
 */
class PublishedAtItem extends DateTimeItem implements FieldProducerItemInterface {

  use FieldProducerTrait;

  /**
   * {@inheritdoc}
   */
  public function resolveFieldItem(FieldItemInterface $item, FieldContext $context) {

    $clone = clone $item;
    $clone->value = $item->published_at_or_now;

    return parent::resolveFieldItem($clone, $context);
  }

}
