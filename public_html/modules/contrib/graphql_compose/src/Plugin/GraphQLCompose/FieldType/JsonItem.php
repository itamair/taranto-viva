<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQLCompose\FieldType;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerItemInterface;
use Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerTrait;
use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeFieldTypeBase;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeFieldType(
 *   id = "json",
 *   type_sdl = "UntypedStructuredData",
 * )
 */
class JsonItem extends GraphQLComposeFieldTypeBase implements FieldProducerItemInterface {
  use FieldProducerTrait;

  /**
   * {@inheritdoc}
   */
  public function resolveFieldItem(FieldItemInterface $item, FieldContext $context) {
    try {
      return Json::decode($item->value);
    }
    catch (InvalidDataTypeException) {
      return $item->value;
    }
  }

}
