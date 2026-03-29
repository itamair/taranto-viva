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
 *   id = "MetaTagScriptAttributes",
 * )
 */
class MetaTagScriptAttributes extends GraphQLComposeSchemaTypeBase {

  /**
   * {@inheritdoc}
   */
  public function getTypes(): array {
    $types = [];

    $types[] = new ObjectType([
      'name' => $this->getPluginId(),
      'description' => (string) $this->t("A meta script element's attributes."),
      'fields' => fn() => [
        'type' => [
          'type' => Type::string(),
          'description' => (string) $this->t('The type attribute of the script tag.'),
        ],
        'src' => [
          'type' => Type::string(),
          'description' => (string) $this->t('The src attribute of the script tag.'),
        ],
        'integrity' => [
          'type' => Type::string(),
          'description' => (string) $this->t('The integrity attribute of the script tag.'),
        ],
      ],
    ]);

    return $types;
  }

}
