<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQL\DataProducer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Entity\TranslatableRevisionableStorageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\graphql\GraphQL\Buffers\EntityRevisionBuffer;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use GraphQL\Deferred;
use GraphQL\Error\UserError;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Loads the entity by revision.
 *
 * @DataProducer(
 *   id = "entity_load_revision",
 *   name = @Translation("Load entity revision"),
 *   description = @Translation("The entity belonging to the current url."),
 *   produces = @ContextDefinition("entity",
 *     label = @Translation("Entity"),
 *   ),
 *   consumes = {
 *     "entity" = @ContextDefinition("any",
 *       label = @Translation("The entity to load revisions from"),
 *     ),
 *     "identifier" = @ContextDefinition("any",
 *       label = @Translation("Revision ID"),
 *       required = FALSE,
 *     ),
 *     "language" = @ContextDefinition("string",
 *       label = @Translation("Language code"),
 *       required = FALSE,
 *     ),
 *   },
 * )
 */
class EntityLoadRevision extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The latest revision identifiers.
   */
  const REVISION_LATEST = [
    'latest',
    'newest',
    'working',
    'working-copy',
  ];

  /**
   * The current revision identifiers.
   */
  const REVISION_CURRENT = [
    'active',
    'current',
    'default',
  ];

  /**
   * Constructs a \Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   The plugin configuration array.
   * @param string $pluginId
   *   The plugin id.
   * @param mixed $pluginDefinition
   *   The plugin definition array.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The language manager service.
   * @param \Drupal\graphql\GraphQL\Buffers\EntityRevisionBuffer $entityRevisionBuffer
   *   The entity revision buffer service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager service.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityRevisionBuffer $entityRevisionBuffer,
    protected LanguageManagerInterface $languageManager,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('graphql.buffer.entity_revision'),
      $container->get('language_manager'),
    );
  }

  /**
   * Resolve the entity revision.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The entity to load revisions from.
   * @param int|string|null $identifier
   *   The revision ID to load.
   * @param string|null $language
   *   The language code to use.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   Cache context.
   */
  public function resolve(?EntityInterface $entity, int|string|null $identifier, ?string $language, FieldContext $context): Deferred|EntityInterface|null {

    $identifier = $identifier ? strtolower((string) $identifier) : NULL;

    if (!$identifier || in_array($identifier, self::REVISION_CURRENT)) {
      return $entity;
    }

    if (!$entity instanceof RevisionableInterface) {
      return $entity;
    }

    $entity_id = $entity->id();
    $entity_type_id = $entity->getEntityTypeId();

    // We need a langcode for getLatestTranslationAffectedRevisionId().
    // Set the default langcode to the current context language.
    // Fall back to the current language.
    $langcode = $language
      ?: $context->getContextLanguage()
      ?: $this->languageManager->getCurrentLanguage()->getId();

    // Quickly resolve the latest revision.
    if (in_array($identifier, self::REVISION_LATEST)) {
      /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
      $storage = $this->entityTypeManager->getStorage($entity_type_id);

      $identifier = ($storage instanceof TranslatableRevisionableStorageInterface)
        ? $storage->getLatestTranslationAffectedRevisionId($entity_id, $langcode)
        : $storage->getLatestRevisionId($entity_id);

      // Did not get a valid revision identifier.
      if (!$identifier) {
        return NULL;
      }
    }

    // Add the entity to the buffer.
    $resolver = $this->entityRevisionBuffer->add($entity_type_id, $identifier);

    return new Deferred(function () use ($resolver, $langcode, $entity_id, $entity_type_id, $context) {

      /** @var \Drupal\Core\Entity\RevisionableInterface|null $revision */
      if (!$revision = $resolver()) {
        // Add cache list tags to invalidate the cache.
        $entity_type = $this->entityTypeManager->getDefinition($entity_type_id, FALSE);
        if ($entity_type) {
          $context->addCacheTags($entity_type->getListCacheTags());
        }

        $context->addCacheTags(['4xx-response']);
        return NULL;
      }

      // Check the revision belongs to the entity.
      if ($revision->id() !== $entity_id) {
        throw new UserError('The requested revision does not belong to the requested entity.');
      }

      $context->setContextValue('revision', $revision->getRevisionId());

      // A specific language was requested.
      // Ensure the revision is translated.
      if ($langcode && $revision instanceof TranslatableInterface && $revision->hasTranslation($langcode) && $langcode !== $revision->language()->getId()) {
        $revision = $revision->getTranslation($langcode);
        $revision->addCacheContexts(["static:language:{$langcode}"]);
      }

      // Check revision access.
      $access = $revision->access('view', NULL, TRUE);
      $context->addCacheableDependency($access);

      return $access->isAllowed() ? $revision : NULL;
    });
  }

}
