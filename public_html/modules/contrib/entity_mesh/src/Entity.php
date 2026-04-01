<?php

namespace Drupal\entity_mesh;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\Routing\Route;

/**
 * Service description.
 */
abstract class Entity {

  /**
   * The mesh type.
   *
   * @var string
   */
  protected $type;

  /**
   * Entity Mesh Repository.
   *
   * @var \Drupal\entity_mesh\RepositoryInterface
   */
  protected $entityMeshRepository;

  /**
   * The config factory manager.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The tracker manager.
   *
   * @var \Drupal\entity_mesh\TrackerManagerInterface
   */
  protected $trackerManager;

  /**
   * Constructs a Menu object.
   *
   * @param \Drupal\entity_mesh\RepositoryInterface $entity_mesh_repository
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity repository.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory manager.
   * @param \Drupal\entity_mesh\TrackerManagerInterface $tracker_manager
   *   The tracker manager.
   */
  public function __construct(RepositoryInterface $entity_mesh_repository, EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager, ConfigFactoryInterface $config_factory, TrackerManagerInterface $tracker_manager) {
    $this->entityMeshRepository = $entity_mesh_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
    $this->config = $config_factory;
    $this->trackerManager = $tracker_manager;
  }

  /**
   * Process entity and store source with targets in database.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to process.
   */
  public function processEntity(EntityInterface $entity) {
    $this->trackerManager->setStatusProcessing($entity);

    $source = $this->createBasicSourceFromEntity($entity);
    if (!$source instanceof SourceInterface) {
      $this->trackerManager->setStatusFailed($entity);
      return;
    }

    // Delete source before process entities.
    $this->entityMeshRepository->deleteSource($source);

    if (!$this->shouldProcessEntity($entity)) {
      // If the entity should not be process, tracker as processed.
      $this->trackerManager->setStatusProcessed($entity);
      return;
    }

    // Check if entity is translatable.
    if ($entity instanceof TranslatableInterface && $entity->isTranslatable()) {
      if ($this->processTranslatableEntity($entity) === FALSE) {
        $this->trackerManager->setStatusFailed($entity);
      }
    }
    else {
      if ($this->processEntityItem($entity) === FALSE) {
        $this->trackerManager->setStatusFailed($entity);
      }
    }

    $this->trackerManager->setStatusProcessed($entity);
  }

  /**
   * Determines if an entity should be processed based on configuration.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   TRUE if the entity should be processed, FALSE otherwise.
   */
  protected function shouldProcessEntity(EntityInterface $entity): bool {
    $config = $this->getMeshConfiguration();

    $enabled_types = $config->get('source_types') ?? [];
    if (!$enabled_types[$entity->getEntityTypeId()] || $enabled_types[$entity->getEntityTypeId()]['enabled'] !== TRUE) {
      return FALSE;
    }

    // Only check bundles if the entity type has bundles.
    if ($this->entityTypeHasBundles($entity->getEntityTypeId())) {
      $enabled_bundles = $enabled_types[$entity->getEntityTypeId()]['bundles'] ?? [];
      if (!empty($enabled_bundles) && !isset($enabled_bundles[$entity->bundle()]) || isset($enabled_bundles[$entity->bundle()]) && $enabled_bundles[$entity->bundle()] !== TRUE) {
        return FALSE;
      }
    }

    // Check if there are any enabled target_types.
    $target_types_internal = $config->get('target_types.internal') ?? [];
    $target_types_external = $config->get('target_types.external') ?? [];

    if (empty($target_types_internal) && empty($target_types_external)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Check if an entity type has bundles.
   *
   * @param string $entity_type_id
   *   The entity type ID to check.
   *
   * @return bool
   *   TRUE if the entity type has bundles, FALSE otherwise.
   */
  protected function entityTypeHasBundles(string $entity_type_id): bool {
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    return $entity_type->hasKey('bundle');
  }

  /**
   * Process a translatable Entity.
   *
   * @param \Drupal\Core\Entity\TranslatableInterface $entity
   *   The entity to process.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  protected function processTranslatableEntity(TranslatableInterface $entity) {
    $translations = $entity->getTranslationLanguages();
    $langcodes = array_keys($translations);
    foreach ($langcodes as $langcode) {
      $translation = $entity->getTranslation($langcode);
      if ($translation instanceof EntityInterface && $this->entityMeshRepository->checkViewAccessEntity($translation)) {
        if ($this->processEntityItem($translation) === FALSE) {
          return FALSE;
        }
      }
    }
    return TRUE;
  }

  /**
   * Process an Entity.
   *
   * Generate the source object with its targets and store it in the database.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to process.
   *
   * @return bool
   *   TRUE if successful, FALSE otherwise.
   */
  protected function processEntityItem(EntityInterface $entity) {
    $source = $this->createSourceFromEntity($entity);
    if ($source instanceof SourceInterface) {
      return $this->entityMeshRepository->saveSource($source);
    }
    return FALSE;
  }

  /**
   * Delete an item from the database.
   *
   * @param string $entity_type
   *   The entity type of the source.
   * @param string $entity_id
   *   The entity id of the source.
   * @param string $type
   *   The type of the source.
   */
  public function deleteItem(string $entity_type, string $entity_id, string $type = '') {
    $source = $this->entityMeshRepository->instanceEmptySource();
    $source->setType($type);
    $source->setSourceEntityType($entity_type);
    $source->setSourceEntityId($entity_id);
    $this->entityMeshRepository->deleteSource($source);
  }

  /**
   * Build a Source object from an Entity, included its targets.
   *
   * Contain all the logic to get all the targets from the entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to process.
   *
   * @return \Drupal\entity_mesh\SourceInterface|null
   *   The source object.
   */
  abstract public function createSourceFromEntity(EntityInterface $entity): ?SourceInterface;

  /**
   * Build a Source object from an Entity, without its targets.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to process.
   *
   * @return \Drupal\entity_mesh\SourceInterface|null
   *   The source object.
   */
  protected function createBasicSourceFromEntity(EntityInterface $entity): ?SourceInterface {
    if ($this->entityMeshRepository->checkViewAccessEntity($entity) === FALSE) {
      return NULL;
    }

    $source = $this->entityMeshRepository->instanceEmptySource();
    $source->setType($this->type);
    $source->setSourceEntityType($entity->getEntityTypeId());
    $source->setSourceEntityBundle($entity->bundle());
    $source->setSourceEntityId((string) $entity->id());
    $source->setSourceEntityLangcode($entity->language()->getId());
    return $source;
  }

  /**
   * Get the front page entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The front page entity or NULL if not found.
   */
  public function frontPage() {
    $config = $this->config->get('system.site');
    $front = $config->get('page.front');
    $query = $this->entityMeshRepository->getDatabaseService()->select('path_alias', 'pa');
    $query->fields('pa', ['path']);
    $or = $query->orConditionGroup()
      ->condition('alias', $front)
      ->condition('path', $front);
    $query->condition($or);
    // @todo !
    // $query->condition('langcode', $langcode);
    $result = $query->execute();
    if (!$result instanceof StatementInterface) {
      return;
    }
    $record = $result->fetchObject();
    if ($record) {
      $path = explode('/', ltrim($record->path, '/'));
      $storage = $this->entityTypeManager->getStorage($path[0]);
      $entity = $storage->load($path[1]);
      if ($entity instanceof EntityInterface) {
        return $entity;
      }
    }
  }

  /**
   * Get the Entity Mesh configuration.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The Entity Mesh configuration object.
   */
  protected function getMeshConfiguration() {
    return $this->config->get('entity_mesh.settings');
  }

  /**
   * Check if the internal target should be processed.
   *
   * @return bool
   *   TRUE if internal targets should be processed, FALSE otherwise.
   */
  protected function ifProcessInternalTarget() {
    $config = $this->getMeshConfiguration();
    $target_types = $config->get('target_types.internal') ?? [];
    return !empty($target_types);
  }

  /**
   * Check if the external target should be processed.
   *
   * @return bool
   *   TRUE if external targets should be processed, FALSE otherwise.
   */
  protected function ifProcessExternalTarget() {
    $config = $this->getMeshConfiguration();
    $target_types_external = $config->get('target_types.external') ?? [];
    return !empty($target_types_external);
  }

  /**
   * Check if the route match belongs to a entity route.
   *
   * @param array $route_match
   *   The route match.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity or NULL.
   */
  protected function checkAndGetEntityFromEntityRoute(array $route_match):? EntityInterface {
    $entity = NULL;

    // Check that we have the parameters that we need.
    if (!isset($route_match['_route']) ||
      !isset($route_match['_route_object']) ||
      !$route_match['_route_object'] instanceof Route
    ) {
      return $entity;
    }

    // Check if the route start with the string 'entity.'.
    // If it is the case, it is probably an entity route.
    if (strpos($route_match['_route'], 'entity.') !== 0) {
      return $entity;
    }

    // If it is an entity route, we can get from parameters of the Route object
    // the name of the parameter.
    /** @var \Symfony\Component\Routing\Route $route_object */
    $route_object = $route_match['_route_object'];
    $parameters = is_array($route_object->getOption('parameters')) ? $route_object->getOption('parameters') : [];

    foreach ($parameters as $name => $value) {
      // There has to be a converter and it has to be the entity converter.
      if (isset($value['converter']) && $value['converter'] === 'paramconverter.entity') {
        $entity = (isset($route_match[$name]) && $route_match[$name] instanceof EntityInterface) ? $route_match[$name] : NULL;
        break;
      }
    }

    return $entity;
  }

  /**
   * Determines if a target should be registered based on configuration.
   *
   * @param \Drupal\entity_mesh\TargetInterface $target
   *   The target to check.
   *
   * @return bool
   *   TRUE if the target should be registered, FALSE otherwise.
   */
  protected function shouldRegisterTarget(TargetInterface $target): bool {
    if (in_array($target->getSubcategory(), ['invalid-url', 'invalid-tel'])) {
      return TRUE;
    }

    $config = $this->getMeshConfiguration();
    $target_types = $config->get('target_types') ?? [];

    // Handle external links.
    if ($target->getLinkType() === 'external') {
      if (!$this->ifProcessExternalTarget()) {
        return FALSE;
      }

      $external_config = $target_types['external'];

      // If bundles are defined, check if the scheme is explicitly enabled.
      if (!empty($external_config['scheme'])) {
        $scheme = $target->getScheme();
        // Protocol-relative URLs (e.g., //example.com) have empty scheme.
        // Treat them as http/https.
        if (empty($scheme)) {
          $scheme = 'http';
        }
        $scheme = $scheme == 'https' ? 'http' : $scheme;
        return isset($external_config['scheme'][$scheme]) && $external_config['scheme'][$scheme] === TRUE;
      }

      // Cover category iframe.
      if (!empty($external_config['categories'])) {
        $category = $target->getCategory();
        return isset($external_config['categories'][$category]) && $external_config['categories'][$category] === TRUE;
      }

      return TRUE;
    }

    // Handle internal links.
    if ($target->getLinkType() === 'internal') {
      if (!$this->ifProcessInternalTarget()) {
        return FALSE;
      }

      if ($target->getSubcategory() === 'broken-link') {
        // If the target is a broken link, we always register it.
        return TRUE;
      }

      $entity_type = $target->getEntityType();

      if (!empty($entity_type)) {
        // Check if entity type is enabled.
        if (!isset($target_types['internal'][$entity_type]['enabled']) ||
          $target_types['internal'][$entity_type]['enabled'] !== TRUE) {
          return FALSE;
        }

        // If bundles are defined, check if the target bundle is enabled.
        $bundles = $target_types['internal'][$entity_type]['bundles'] ?? [];
        if (!empty($bundles)) {
          $bundle = $target->getEntityBundle();
          return isset($bundles[$bundle]) && $bundles[$bundle] === TRUE;
        }
      }

      return TRUE;
    }

    return FALSE;
  }

}
