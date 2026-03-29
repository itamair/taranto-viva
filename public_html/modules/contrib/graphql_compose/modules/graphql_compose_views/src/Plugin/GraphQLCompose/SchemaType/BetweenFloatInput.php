<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_views\Plugin\GraphQLCompose\SchemaType;

use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeSchemaTypeBase;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeSchemaType(
 *   id = "BetweenFloatInput",
 * )
 */
class BetweenFloatInput extends GraphQLComposeSchemaTypeBase {

  /**
   * {@inheritdoc}
   */
  public function getTypes(): array {
    $types = [];

    $types[] = new InputObjectType([
      'name' => $this->getPluginId(),
      'description' => (string) $this->t('Input for filter exposed with operator "between".'),
      'fields' => fn() => [
        'min' => [
          'type' => Type::float(),
          'description' => (string) $this->t('The minimum value of the range.'),
        ],
        'max' => [
          'type' => Type::float(),
          'description' => (string) $this->t('The maximum value of the range.'),
        ],
      ],
    ]);

    return $types;
  }

}
