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
 *   id = "Language",
 * )
 */
class LanguageType extends GraphQLComposeSchemaTypeBase {

  /**
   * {@inheritdoc}
   */
  public function getTypes(): array {
    $types = [];

    $types[] = new ObjectType([
      'name' => $this->getPluginId(),
      'description' => (string) $this->t('A language definition provided by the CMS.'),
      'fields' => fn() => [
        'id' => [
          'type' => Type::id(),
          'description' => (string) $this->t('The language code.'),
        ],
        'name' => [
          'type' => Type::string(),
          'description' => (string) $this->t('The language name.'),
        ],
        'direction' => [
          'type' => Type::string(),
          'description' => (string) $this->t('The language direction.'),
        ],
      ],
    ]);

    return $types;
  }

  /**
   * {@inheritdoc}
   */
  public function getExtensions(): array {
    $extensions = parent::getExtensions();

    if (!$this->languageManager->isMultilingual()) {
      return $extensions;
    }

    $extensions[] = new ObjectType([
      'name' => 'SchemaInformation',
      'fields' => fn () => [
        'languages' => [
          'type' => Type::nonNull(Type::listOf(Type::nonNull(static::type('Language')))),
          'description' => (string) $this->t('List of languages available.'),
        ],
      ],
    ]);

    return $extensions;
  }

}
