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
 *   id = "ViewFilter",
 * )
 */
class ViewFilter extends GraphQLComposeSchemaTypeBase {

  /**
   * {@inheritdoc}
   */
  public function getTypes(): array {
    $types = [];

    $types[] = new ObjectType([
      'name' => $this->getPluginId(),
      'description' => (string) $this->t('An exposed filter option for the view.'),
      'fields' => fn() => [
        'id' => [
          'type' => Type::nonNull(Type::id()),
          'description' => (string) $this->t('The filter identifier.'),
        ],
        'plugin' => [
          'type' => Type::nonNull(Type::string()),
          'description' => (string) $this->t('The filter plugin type.'),
        ],
        'type' => [
          'type' => Type::nonNull(Type::string()),
          'description' => (string) $this->t('The filter element type.'),
        ],
        'operator' => [
          'type' => Type::nonNull(Type::string()),
          'description' => (string) $this->t('The filter operator.'),
        ],
        'label' => [
          'type' => Type::string(),
          'description' => (string) $this->t('The filter element label.'),
        ],
        'description' => [
          'type' => Type::string(),
          'description' => (string) $this->t('The filter element description.'),
        ],
        'required' => [
          'type' => Type::nonNull(Type::boolean()),
          'description' => (string) $this->t('Whether the filter is required.'),
        ],
        'multiple' => [
          'type' => Type::nonNull(Type::boolean()),
          'description' => (string) $this->t('Whether the filter allows multiple values.'),
        ],
        'value' => [
          'type' => static::type('UntypedStructuredData'),
          'description' => (string) $this->t('The value for the filter. Could be an array for multiple values.'),
        ],
        'options' => [
          'type' => static::type('UntypedStructuredData'),
          'description' => (string) $this->t('The filter element options if any are defined.'),
        ],
        'attributes' => [
          'type' => Type::nonNull(static::type('UntypedStructuredData')),
          'description' => (string) $this->t('The filter element attributes.'),
        ],
      ],
    ]);

    return $types;
  }

}
