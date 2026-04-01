<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_views\Plugin\GraphQLCompose\SchemaType;

use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeSchemaTypeBase;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeSchemaType(
 *   id = "ViewReference",
 * )
 */
class ViewReference extends GraphQLComposeSchemaTypeBase {

  /**
   * {@inheritdoc}
   */
  public function getTypes(): array {
    $types = [];

    $types[] = new ObjectType([
      'name' => $this->getPluginId(),
      'description' => (string) $this->t('A reference to an embedded view'),
      'fields' => fn() => [
        'view' => [
          'type' => Type::nonNull(Type::string()),
          'description' => (string) $this->t('The machine name of the view.'),
        ],
        'display' => [
          'type' => Type::nonNull(Type::string()),
          'description' => (string) $this->t('The machine name of the display.'),
        ],
        'contextualFilter' => [
          'type' => Type::listOf(Type::nonNull(Type::string())),
          'description' => (string) $this->t('The contextual filter values used.'),
        ],
        'pageSize' => [
          'type' => Type::int(),
          'description' => (string) $this->t('How many results per page.'),
        ],
        'query' => [
          'type' => Type::string(),
          'description' => (string) $this->t('The name of the query used to fetch the data, if the view is a GraphQL display.'),
        ],
      ],
    ]);

    return $types;
  }

}
