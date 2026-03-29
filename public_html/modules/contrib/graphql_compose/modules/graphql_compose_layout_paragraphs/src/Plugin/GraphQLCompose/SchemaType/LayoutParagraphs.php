<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_layout_paragraphs\Plugin\GraphQLCompose\SchemaType;

use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeSchemaTypeBase;
use GraphQL\Type\Definition\ObjectType;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeSchemaType(
 *   id = "LayoutParagraphs",
 * )
 */
class LayoutParagraphs extends GraphQLComposeSchemaTypeBase {

  /**
   * {@inheritdoc}
   */
  public function getTypes(): array {
    $types = [];

    $types[] = new ObjectType([
      'name' => $this->getPluginId(),
      'description' => (string) $this->t('If this component has been designed by a User extra information will be available here.'),
      'fields' => fn() => [
        'layout' => [
          'type' => static::type('Layout'),
          'description' => (string) $this->t('The layout definition for this component.'),
        ],
        'position' => [
          'type' => static::type('LayoutParagraphsPosition'),
          'description' => (string) $this->t('Detail on where this component is suggested to be placed within the parent component.'),
        ],
      ],
    ]);

    return $types;
  }

}
