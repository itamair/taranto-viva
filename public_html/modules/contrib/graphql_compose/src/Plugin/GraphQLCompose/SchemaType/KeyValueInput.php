<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQLCompose\SchemaType;

use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeSchemaTypeBase;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeSchemaType(
 *   id = "KeyValueInput",
 * )
 */
class KeyValueInput extends GraphQLComposeSchemaTypeBase {

  /**
   * {@inheritdoc}
   */
  public function getTypes(): array {
    $types = [];

    $types[] = new InputObjectType([
      'name' => $this->getPluginId(),
      'description' => (string) $this->t('Generic input for key-value pairs.'),
      'fields' => fn() => [
        'key' => Type::nonNull(Type::string()),
        'value' => Type::string(),
      ],
    ]);

    return $types;
  }

}
