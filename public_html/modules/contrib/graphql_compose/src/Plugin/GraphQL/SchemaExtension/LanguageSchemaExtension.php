<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQL\SchemaExtension;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistryInterface;

/**
 * Languages's are a common occurrence. Map LanguageInterface objects in schema.
 *
 * @SchemaExtension(
 *   id = "graphql_compose_language",
 *   name = "GraphQL Compose Languages",
 *   description = @Translation("Add language support to schema."),
 *   schema = "graphql_compose",
 * )
 *
 * @internal
 */
class LanguageSchemaExtension extends ResolverOnlySchemaExtensionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function registerResolvers(ResolverRegistryInterface $registry): void {
    $builder = new ResolverBuilder();

    $registry->addFieldResolver(
      'Language',
      'id',
      $builder->callback(fn (LanguageInterface $language) => $language->getId())
    );

    $registry->addFieldResolver(
      'Language',
      'name',
      $builder->callback(fn (LanguageInterface $language) => $language->getName())
    );

    $registry->addFieldResolver(
      'Language',
      'direction',
      $builder->callback(fn (LanguageInterface $language) => $language->getDirection())
    );

    // Add language resolvers.
    $registry->addTypeResolver(
      'Language',
      fn($language) => $language instanceof LanguageInterface ? $language : NULL,
    );

    if ($this->languageManager->isMultilingual()) {
      $registry->addFieldResolver(
        'SchemaInformation',
        'languages',
        $builder->callback(fn () => $this->languageManager->getLanguages())
      );

      foreach ($this->gqlEntityTypeManager->getPluginInstances() as $entity_type) {
        foreach ($entity_type->getBundles() as $bundle) {

          if (!$bundle->isTranslatableContent()) {
            continue;
          }

          $registry->addFieldResolver(
            $bundle->getTypeSdl(),
            'translations',
            $builder->compose(
              $builder->produce('entity_translations')
                ->map('entity', $builder->fromParent()),
              $builder->callback(
                fn (?array $translations) => array_filter($translations ?: [])
              )
            )
          );
        }
      }

      $registry->addFieldResolver(
        'Translation',
        'title',
        $builder->produce('entity_label')
          ->map('entity', $builder->fromParent())
      );

      $registry->addFieldResolver(
        'Translation',
        'langcode',
        $builder->produce('entity_language')
          ->map('entity', $builder->fromParent())
      );

      $registry->addFieldResolver(
        'Translation',
        'path',
        $builder->cond([
          [
            $builder->callback(fn (EntityInterface $entity) => $entity->isNew()),
            $builder->fromValue(NULL),
          ], [
            $builder->fromValue(TRUE),
            $builder->compose(
              $builder->produce('entity_url')
                ->map('entity', $builder->fromParent()),

              $builder->produce('url_path')
                ->map('url', $builder->fromParent()),
            ),
          ],
        ]),
      );
    }

  }

}
