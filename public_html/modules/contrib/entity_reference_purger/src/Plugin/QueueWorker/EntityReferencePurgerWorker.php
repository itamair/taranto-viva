<?php

namespace Drupal\entity_reference_purger\Plugin\QueueWorker;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Entity reference purger queue worker.
 *
 * @QueueWorker(
 *   id = "entity_reference_purger",
 *   title = @Translation("Entity reference purger queue"),
 *   cron = {"time" = 60}
 * )
 */
class EntityReferencePurgerWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The date time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * Constructs a new class instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The date time service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $current_user,
    TimeInterface $time
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->time = $time;
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
      $container->get('current_user'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if ($data) {
      $entity_type = $data['entity_type'];
      $entity_id = $data['entity_id'];
      $field_name = $data['field_name'];

      $entity_storage = $this->entityTypeManager->getStorage($entity_type);
      $entity = $entity_storage->load($entity_id);

      if ($entity instanceof FieldableEntityInterface) {
        $field = $entity->get($field_name);
        $matches = [];

        if (!empty($data['target_id'])) {
          $queued_target_id = $data['target_id'];

          // Search for all items pointing to the target, regardless of the
          // original delta.
          foreach ($field as $delta => $item) {
            if ($item->get('target_id')->getValue() === $queued_target_id) {
              $matches[] = $delta;
            }
          }
        }
        // @todo Remove this in the future.
        elseif (isset($data['delta'])) {
          if ($field->count() > $data['delta']) {
            // No target ID stored, but a delta. Most certainly from an older
            // version of this module.
            $matches[] = $data['delta'];
          }
        }

        if ($matches) {
          foreach (array_reverse($matches) as $delta) {
            $field->removeItem($delta);
          }

          // Check if the entity type supports revisions and create a new
          // revision if so.
          if ($entity instanceof RevisionLogInterface) {
            $entity->setNewRevision(TRUE);
            $entity->setRevisionUserId($this->currentUser->id());
            $entity->setRevisionCreationTime($this->time->getRequestTime());
            $entity->setRevisionLogMessage($this->t('Removed orphaned entity reference via Entity Reference Purger module. Removed delta: @delta for field: @field_name.', [
              '@delta' => implode(', ', $matches),
              '@field_name' => $field_name,
            ]));
          }

          $entity->save();
        }
      }
    }
  }

}
