<?php

namespace Drupal\ai_tmgmt\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Queue\QueueFactory;
use Drupal\tmgmt\Entity\JobItem;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\JobItemInterface;

/**
 * Hooks for the AI TMGMT module.
 */
class AiTranslatorHook {

  /**
   * Constructs the AI TMGMT Hook class.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   The queue factory.
   */
  public function __construct(
    protected QueueFactory $queueFactory,
  ) {
  }

  /**
   * Implements hook_entity_delete().
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    if (
      $entity instanceof JobInterface
      || $entity instanceof JobItemInterface
    ) {
      // Delete all queue items for this job or job item.
      $queue = $this->queueFactory->get('ai_translator_worker', TRUE);
      $items = [];
      while ($item = $queue->claimItem(60 * 60)) {
        $delete = FALSE;

        if ($job_item_id = $item->data['job_item_id'] ?? NULL) {
          // Check if the queue item is for this job item.
          if ($entity instanceof JobItemInterface) {
            if ($entity->id() === $job_item_id) {
              $delete = TRUE;
            }
          }
          // Check if the queue item is for this job.
          elseif ($entity instanceof JobInterface) {
            if ($jobItem = JobItem::load($job_item_id)) {
              if ($entity->id() === $jobItem->getJobId()) {
                $delete = TRUE;
              }
            }
          }
        }

        if ($delete) {
          $queue->deleteItem($item);
          continue;
        }

        $items[] = $item;
      }

      // Release all other items.
      foreach ($items as $item) {
        $queue->releaseItem($item);
      }
    }
  }

}
