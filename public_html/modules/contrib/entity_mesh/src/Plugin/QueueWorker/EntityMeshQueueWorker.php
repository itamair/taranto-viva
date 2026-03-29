<?php

namespace Drupal\entity_mesh\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\QueueWorkerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes tasks for entity_mesh module.
 *
 * @todo Remove queue worker.
 *
 * @QueueWorker(
 *   id = "entity_mesh_queue_worker",
 *   title = @Translation("Entity Mesh Queue Worker"),
 *   cron = {"time" = 60}
 * )
 */
final class EntityMeshQueueWorker extends QueueWorkerBase implements QueueWorkerInterface, ContainerFactoryPluginInterface {

  /**
   * Migration manager.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $object = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
    $object->container = $container;
    $object->logger = $container->get('logger.factory')->get('entity_mesh');
    return $object;
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if ($data['task_type'] === 'delete_item') {
      $this->deleteItem($data);
      return;
    }

    if ($data['task_type'] === 'process_item') {
      $this->processEntity($data);
    }
  }

  /**
   * Remove an item.
   *
   * @param array $data
   *   The data.
   */
  protected function deleteItem(array $data) {
    $entity_mesh_service = $this->container->get($data['service']);
    $entity_mesh_service->{$data['service_method']}($data['entity'], $data['id']);
  }

  /**
   * Process entity.
   *
   * @param array $data
   *   The data.
   */
  protected function processEntity($data) {
    $entity_mesh_service = $this->container->get($data['service']);
    try {
      $storage = $this->container->get('entity_type.manager')->getStorage($data['entity']);
    }
    catch (\Exception $e) {
      $this->logger->error('Error getting storage: @message', ['@message' => $e->getMessage()]);
      return;
    }

    $entity = $storage->load($data['id']);
    if (!$entity) {
      $this->logger->error('Error loading entity @entity @id', ['@entity' => $data['entity'], '@id' => $data['id']]);
      return;
    }

    $entity_mesh_service->{$data['service_method']}($entity);
  }

}
