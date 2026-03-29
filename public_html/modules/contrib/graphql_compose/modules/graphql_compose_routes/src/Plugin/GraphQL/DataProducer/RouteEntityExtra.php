<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_routes\Plugin\GraphQL\DataProducer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\graphql\GraphQL\Buffers\EntityBuffer;
use Drupal\graphql\GraphQL\Buffers\EntityRevisionBuffer;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\graphql_compose_routes\GraphQL\Buffers\EntityPreviewBuffer;
use GraphQL\Deferred;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Loads the entity associated with the current URL.
 *
 * @DataProducer(
 *   id = "route_entity_extra",
 *   name = @Translation("Load entity, preview or revision by url"),
 *   description = @Translation("The entity belonging to the current url."),
 *   produces = @ContextDefinition("entity",
 *     label = @Translation("Entity"),
 *   ),
 *   consumes = {
 *     "url" = @ContextDefinition("any",
 *       label = @Translation("The URL"),
 *     ),
 *     "language" = @ContextDefinition("string",
 *       label = @Translation("Language"),
 *       required = FALSE,
 *     ),
 *   },
 * )
 */
class RouteEntityExtra extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The language to translate entities into.
   *
   * @var string|null
   */
  protected ?string $langcode;

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
   * @param \Drupal\graphql\GraphQL\Buffers\EntityBuffer $entityBuffer
   *   The entity buffer service.
   * @param \Drupal\graphql\GraphQL\Buffers\EntityRevisionBuffer $entityRevisionBuffer
   *   The entity revision buffer service.
   * @param \Drupal\graphql_compose_routes\GraphQL\Buffers\EntityPreviewBuffer $entityPreviewBuffer
   *   The entity preview buffer service.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityBuffer $entityBuffer,
    protected EntityRevisionBuffer $entityRevisionBuffer,
    protected EntityPreviewBuffer $entityPreviewBuffer,
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
      $container->get('graphql.buffer.entity'),
      $container->get('graphql.buffer.entity_revision'),
      $container->get('graphql_compose_routes.buffer.entity_preview'),
    );
  }

  /**
   * Convert a URL to an entity via buffer.
   *
   * @param \Drupal\Core\Url|null $url
   *   The URL to resolve.
   * @param string|null $langcode
   *   The language code to use.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   Cache context.
   */
  public function resolve(?Url $url, ?string $langcode, FieldContext $context): ?Deferred {
    if (!$url instanceof Url) {
      return NULL;
    }

    // Set the default langcode to the current context language.
    $this->langcode = $langcode ?: $context->getContextLanguage();

    [, $type] = explode('.', $url->getRouteName());
    $parameters = $url->getRouteParameters();

    // Previews.
    if (array_key_exists($type . '_preview', $parameters)) {
      return $this->resolvePreview($type, $parameters, $context);
    }

    // Revisions.
    if (array_key_exists($type . '_revision', $parameters)) {
      return $this->resolveRevision($type, $parameters, $context);
    }

    // Eg /user - What is that to the Schema? Theres no data.
    // It's a route internal, but not an entity.
    if (empty($parameters[$type])) {
      return NULL;
    }

    // Entities.
    return $this->resolveEntity($type, $parameters, $context);
  }

  /**
   * Resolve an entity.
   *
   * @param string $type
   *   The entity type.
   * @param array $parameters
   *   The URL parameters.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   Cache context.
   *
   * @return \GraphQL\Deferred
   *   The deferred entity.
   */
  protected function resolveEntity(string $type, array $parameters, FieldContext $context): Deferred {
    $entity_id = $parameters[$type];
    $resolver = $this->entityBuffer->add($type, $entity_id);

    return new Deferred(function () use ($type, $resolver, $context) {
      if (!$entity = $resolver()) {
        return $this->resolveNotFound($type, $context);
      }

      $entity = $this->getTranslated($entity);

      $access = $entity->access('view', NULL, TRUE);
      $context->addCacheableDependency($access);

      return $access->isAllowed() ? $entity : NULL;
    });
  }

  /**
   * Resolve a preview entity.
   *
   * @param string $type
   *   The entity type.
   * @param array $parameters
   *   The URL parameters.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   Cache context.
   *
   * @return \GraphQL\Deferred
   *   The deferred entity.
   */
  protected function resolvePreview(string $type, array $parameters, FieldContext $context): Deferred {
    $preview_id = $parameters[$type . '_preview'];
    $resolver = $this->entityPreviewBuffer->add($type, $preview_id);

    return new Deferred(function () use ($type, $resolver, $context) {
      if (!$entity = $resolver()) {
        return $this->resolveNotFound($type, $context);
      }

      $entity = $this->getTranslated($entity);

      $access = $entity->access('view', NULL, TRUE);
      $context->addCacheableDependency($access);

      // Disable caching for accessible preview entities.
      if ($access->isAllowed()) {
        $context->setContextValue('preview', TRUE);
        $context->mergeCacheMaxAge(0);
        return $entity;
      }
      return NULL;
    });
  }

  /**
   * Resolve a preview revision.
   *
   * @param string $type
   *   The entity type.
   * @param array $parameters
   *   The URL parameters.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   Cache context.
   *
   * @return \GraphQL\Deferred
   *   The deferred entity.
   */
  protected function resolveRevision(string $type, array $parameters, FieldContext $context): Deferred {
    $revision_id = $parameters[$type . '_revision'];
    $resolver = $this->entityRevisionBuffer->add($type, $revision_id);

    return new Deferred(function () use ($type, $resolver, $context) {
      if (!$entity = $resolver()) {
        return $this->resolveNotFound($type, $context);
      }

      $entity = $this->getTranslated($entity);

      $access = $entity->access('view', NULL, TRUE);
      $context->addCacheableDependency($access);

      return $access->isAllowed() ? $entity : NULL;
    });
  }

  /**
   * Set the language on an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to set the language on.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity with the language set.
   */
  private function getTranslated(EntityInterface $entity) {
    // Get the correct translation.
    if ($entity instanceof TranslatableInterface && $entity->hasTranslation($this->langcode) && $this->langcode !== $entity->language()->getId()) {
      $entity = $entity->getTranslation($this->langcode);
      $entity->addCacheContexts(["static:language:{$this->langcode}"]);
    }

    return $entity;
  }

  /**
   * Resolve a not found entity.
   *
   * If there is no entity with this id, add the list cache tags so that
   * the cache entry is purged whenever a new entity of this type is
   * saved.
   *
   * @param string $type
   *   The entity type.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   Cache context.
   *
   * @return null
   *   Always null.
   */
  private function resolveNotFound($type, FieldContext $context) {

    $type = $this->entityTypeManager->getDefinition($type, FALSE);
    if ($type) {
      $context->addCacheTags($type->getListCacheTags());
    }

    $context->addCacheTags(['4xx-response']);

    return NULL;
  }

}
