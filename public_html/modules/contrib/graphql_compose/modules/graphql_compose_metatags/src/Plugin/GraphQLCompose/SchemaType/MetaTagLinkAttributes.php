<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_metatags\Plugin\GraphQLCompose\SchemaType;

use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeSchemaTypeBase;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeSchemaType(
 *   id = "MetaTagLinkAttributes",
 * )
 */
class MetaTagLinkAttributes extends GraphQLComposeSchemaTypeBase {

  /**
   * {@inheritdoc}
   */
  public function getTypes(): array {
    $types = [];

    $types[] = new ObjectType([
      'name' => $this->getPluginId(),
      'description' => (string) $this->t("A meta link element's attributes."),
      'fields' => fn() => [
        'href' => [
          'type' => Type::string(),
          'description' => (string) $this->t('Specifies the location of the linked document.'),
        ],
        'hreflang' => [
          'type' => Type::string(),
          'description' => (string) $this->t('Specifies the location of the linked document.'),
        ],
        'rel' => [
          'type' => Type::string(),
          'description' => (string) $this->t('Specifies the relationship between the current document and the linked document.'),
        ],
        'media' => [
          'type' => Type::string(),
          'description' => (string) $this->t('Specifies on what device the linked document will be displayed.'),
        ],
        'sizes' => [
          'type' => Type::string(),
          'description' => (string) $this->t('Specifies the size of the linked resource. Only for rel="icon".'),
        ],
        'type' => [
          'type' => Type::string(),
          'description' => (string) $this->t('Specifies the media type of the linked document.'),
        ],
      ],
    ]);

    return $types;
  }

}
