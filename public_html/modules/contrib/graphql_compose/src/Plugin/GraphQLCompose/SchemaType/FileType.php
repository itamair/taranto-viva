<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQLCompose\SchemaType;

use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeSchemaTypeBase;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeSchemaType(
 *   id = "File",
 * )
 */
class FileType extends GraphQLComposeSchemaTypeBase {

  /**
   * {@inheritdoc}
   */
  public function getTypes(): array {
    $types = [];

    $types[] = new ObjectType([
      'name' => $this->getPluginId(),
      'description' => (string) $this->t('A file object to represent an managed file.'),
      'fields' => fn() => [
        'name' => [
          'type' => Type::string(),
          'description' => (string) $this->t('The name of the file.'),
        ],
        'url' => [
          'type' => Type::nonNull(Type::string()),
          'description' => (string) $this->t('The URL of the file.'),
        ],
        'size' => [
          'type' => Type::nonNull(Type::int()),
          'description' => (string) $this->t('The size of the file in bytes.'),
        ],
        'mime' => [
          'type' => Type::string(),
          'description' => (string) $this->t('The mime type of the file.'),
        ],
        'description' => [
          'type' => Type::string(),
          'description' => (string) $this->t('The description of the file.'),
        ],
      ],
    ]);

    return $types;
  }

}
