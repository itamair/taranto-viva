<?php

namespace Drupal\warmer_entity\Plugin\warmer;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Utility\Error;
use Drupal\warmer\Plugin\WarmerPluginBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The cache warmer for the built-in entity cache.
 *
 * @Warmer(
 *   id = "entity",
 *   label = @Translation("Entity"),
 *   description = @Translation("Loads entities from the selected entity types & bundles to warm the entity cache.")
 * )
 */
final class EntityWarmer extends WarmerPluginBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * The in-memory static entity cache.
   *
   * @var \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface
   */
  private MemoryCacheInterface $entityMemoryCache;

  /**
   * Entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $bundleInfo;

  /**
   * The logger service.
   */
  protected LoggerInterface $logger;

  /**
   * The list of all item IDs for all entities in the system.
   *
   * Consists of <entity-type-id>:<entity-id>.
   */
  private array $iids = [];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    assert($instance instanceof EntityWarmer);
    $instance->setEntityTypeManager($container->get('entity_type.manager'));
    $instance->setEntityMemoryCache($container->get('entity.memory_cache'));
    $instance->setEntityTypeBundleInfoManager($container->get('entity_type.bundle.info'));
    $instance->setLogger($container->get('logger.factory')->get('warmer'));
    return $instance;
  }

  /**
   * Injects the entity type bundle info service.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_info
   *   The entity type bundle info service.
   */
  public function setEntityTypeBundleInfoManager(EntityTypeBundleInfoInterface $bundle_info): void {
    $this->bundleInfo = $bundle_info;
  }

  /**
   * Injects the entity type manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager): void {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Injects the entity memory cache.
   *
   * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface $memory_cache
   *   The memory cache.
   */
  public function setEntityMemoryCache(MemoryCacheInterface $memory_cache): void {
    $this->entityMemoryCache = $memory_cache;
  }

  /**
   * Injects the logger.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger.
   */
  public function setLogger(LoggerInterface $logger): void {
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids = []) {
    $ids_per_type = array_reduce($ids, function ($carry, $id) {
      [$entity_type_id, $entity_id] = explode(':', $id);
      if (empty($carry[$entity_type_id])) {
        $carry[$entity_type_id] = [];
      }
      $carry[$entity_type_id][] = $entity_id;
      return $carry;
    }, []);
    $output = [];
    foreach ($ids_per_type as $entity_type_id => $entity_ids) {
      try {
        $output += $this->entityTypeManager
          ->getStorage($entity_type_id)
          ->loadMultiple($entity_ids);
        // \Drupal\Core\Entity\EntityStorageBase::buildCacheId() is protected,
        // so we blindly reset the whole static cache instead of specific IDs.
        $this->entityMemoryCache->deleteAll();
      }
      catch (PluginException $exception) {
        Error::logException($this->logger, $exception);
      }
      catch (DatabaseExceptionWrapper $exception) {
        Error::logException($this->logger, $exception);
      }
    }
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function warmMultiple(array $items = []) {
    // The entity load already warms the entity cache. Do nothing.
    return count($items);
  }

  /**
   * {@inheritdoc}
   *
   * @todo This is a naive implementation.
   */
  public function buildIdsBatch($cursor) {
    $configuration = $this->getConfiguration();
    if (empty($this->iids) && !empty($configuration['entity_types'])) {
      $entity_bundle_pairs = array_filter(array_values($configuration['entity_types']));
      sort($entity_bundle_pairs);
      $published_only = $configuration['published_only'] ?? FALSE;
      $this->iids = array_reduce($entity_bundle_pairs, function ($iids, $entity_bundle_pair) use ($published_only) {
        [$entity_type_id, $bundle] = explode(':', $entity_bundle_pair);
        $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
        $bundle_key = $entity_type->getKey('bundle');
        $id_key = $entity_type->getKey('id');
        $query = $this->entityTypeManager
          ->getStorage($entity_type_id)
          ->getQuery()
          ->accessCheck(FALSE);
        if (!empty($id_key)) {
          $query->sort($id_key);
        }
        if (!empty($bundle_key)) {
          $query->condition($bundle_key, $bundle);
        }
        if ($published_only) {
          $query->accessCheck(TRUE);
          if ($entity_type->hasKey('published')) {
            $published_key = $entity_type->getKey('published');
            $query->condition($published_key, TRUE);
          }
        }
        $results = $query->execute();
        $entity_ids = array_filter((array) array_values($results));
        $iids = array_merge($iids, array_map(
          function ($id) use ($entity_type_id) {
            return sprintf('%s:%s', $entity_type_id, $id);
          },
          $entity_ids
        ));
        return $iids;
      }, []);
    }
    $cursor_position = is_null($cursor) ? -1 : array_search($cursor, $this->iids);
    if ($cursor_position === FALSE) {
      return [];
    }
    return array_slice($this->iids, $cursor_position + 1, (int) $this->getBatchSize());
  }

  /**
   * {@inheritdoc}
   */
  public function addMoreConfigurationFormElements(array $form, SubformStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_info */
    $bundle_info = $this->bundleInfo;
    $options = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
      $bundles = $bundle_info->getBundleInfo($entity_type->id());
      $label = (string) $entity_type->getLabel();
      $entity_type_id = $entity_type->id();
      $options[$label] = [];
      foreach ($bundles as $bundle_id => $bundle_data) {
        $options[$label][sprintf('%s:%s', $entity_type_id, $bundle_id)] = $bundle_data['label'];
      }
    }
    $configuration = $this->getConfiguration();
    $form['entity_types'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity Types'),
      '#description' => $this->t('Enable the entity types to warm asynchronously.'),
      '#options' => $options,
      '#default_value' => empty($configuration['entity_types']) ? [] : $configuration['entity_types'],
      '#multiple' => TRUE,
      '#attributes' => ['style' => 'min-height: 60em;'],
    ];

    $form['published_only'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Warm only published entities'),
      '#description' => $this->t('Will check publishing status for entities that implement EntityPublishedInterface'),
      '#default_value' => $configuration['published_only'] ?? FALSE,
    ];

    return $form;
  }

}
