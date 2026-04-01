<?php

namespace Drupal\entity_mesh\Commands;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\entity_mesh\Batches\EntityTrackerBatch;
use Drupal\entity_mesh\Batches\NodeBatch;
use Drupal\entity_mesh\EntityRender;
use Drush\Commands\DrushCommands;
use Drush\Log\DrushLoggerManager;

/**
 * A Drush command file.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class EntityMeshCommands extends DrushCommands {

  /**
   * Logger Factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity render.
   *
   * @var \Drupal\entity_mesh\EntityRender
   */
  protected EntityRender $entityRender;

  /**
   * Constructs a new MyCustomDrushCommand object.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\entity_mesh\EntityRender $entity_render
   *   Entity render.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    EntityTypeManagerInterface $entity_type_manager,
    EntityRender $entity_render,
  ) {
    // parent::__construct();
    $this->loggerFactory = $logger_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRender = $entity_render;
  }

  /**
   * Tracks entities for Entity Mesh processing.
   *
   * Adds all configured entities to the tracker queue. These entities
   * will be pending until processed with entity-mesh:process-batch.
   *
   * @usage entity-mesh:track
   *   Track all configured entities
   *
   * @command entity-mesh:track
   * @aliases entity-mesh-track
   */
  public function track() {
    $logger = $this->logger();
    if ($logger instanceof DrushLoggerManager) {
      $logger->notice('Starting entity tracking batch...');
    }

    $batch = EntityTrackerBatch::generateBatch();
    batch_set($batch->toArray());
    drush_backend_batch_process();

    if ($logger instanceof DrushLoggerManager) {
      $logger->notice('Entity tracking batch completed.');
    }
  }

  /**
   * Processes tracked entities from the tracker queue.
   *
   * Processes all pending entities that were previously added to the
   * tracker using entity-mesh:track command.
   *
   * @usage entity-mesh:process-batch
   *   Process all tracked entities
   *
   * @command entity-mesh:process-batch
   * @aliases entity-mesh-pb
   */
  public function processBatch() {
    $logger = $this->logger();
    if ($logger instanceof DrushLoggerManager) {
      $logger->notice('Starting entity processing batch...');
    }

    $batch = NodeBatch::generateBatch();
    batch_set($batch->toArray());
    drush_backend_batch_process();

    if ($logger instanceof DrushLoggerManager) {
      $logger->notice('Entity processing batch completed.');
    }
  }

  /**
   * Process Entity Mesh data.
   *
   * @param string $type
   *   Mesh type.
   * @param string $id
   *   Entity ID.
   *
   * @usage entity-mesh:process node 12
   *   Generate node mesh only
   *
   * @command entity-mesh:process
   * @aliases entity-mesh-pr
   */
  public function processEntity(string $type, string $id) {
    $logger = $this->logger();
    $entity = $this->entityTypeManager->getStorage($type)->load($id);
    if ($entity instanceof EntityInterface) {
      $this->entityRender->processEntity($entity);
    }
    else {
      if ($logger instanceof DrushLoggerManager) {
        $logger->error('Entity not found.');
      }
      return;
    }
    if ($logger instanceof DrushLoggerManager) {
      $logger->notice('Entity process ended.');
    }
  }

}
