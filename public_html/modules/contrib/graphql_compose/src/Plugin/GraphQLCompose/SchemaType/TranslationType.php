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
 *   id = "Translation",
 * )
 */
class TranslationType extends GraphQLComposeSchemaTypeBase {

  /**
   * {@inheritdoc}
   */
  public function getTypes(): array {
    $types = [];

    if (!$this->languageManager->isMultilingual()) {
      return $types;
    }

    $types[] = new ObjectType([
      'name' => $this->getPluginId(),
      'description' => (string) $this->t('Available translations for content.'),
      'fields' => fn() => [
        'title' => [
          'type' => Type::string(),
          'description' => (string) $this->t('The title of the translation.'),
        ],
        'langcode' => [
          'type' => Type::nonNull(static::type('Language')),
          'description' => (string) $this->t('The language of the translation.'),
        ],
        'path' => [
          'type' => Type::string(),
          'description' => (string) $this->t('The path to the translated content.'),
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

    foreach ($this->gqlEntityTypeManager->getPluginInstances() as $entity_type) {
      foreach ($entity_type->getBundles() as $bundle) {

        if (!$bundle->isTranslatableContent()) {
          continue;
        }

        $extensions[] = new ObjectType([
          'name' => $bundle->getTypeSdl(),
          'fields' => fn () => [
            'translations' => [
              'type' => Type::nonNull(Type::listOf(Type::nonNull(static::type($this->getPluginId())))),
              'description' => (string) $this->t('Available translations for content.'),
            ],
          ],
        ]);
      }
    }

    return $extensions;
  }

}
