<?php

namespace Drupal\custom_module;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\node\NodeInterface;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Generates a TerritorialReportActiveLevelFieldItemList.
 */
class ActiveStatusFieldItemList extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * Whether the value has been calculated.
   *
   * @var bool
   */
  protected bool $isCalculated = FALSE;

  /**
   * {@inheritdoc}
   *
   * Generate the Active Status Value for specific Content Types.
   *
   * The status defaults to FALSE (inactive) and becomes TRUE only for nodes of
   * the listed bundles whose 'field_date_range' is currently running:
   * - start date already reached (start <= now), and
   * - end date, if any, not passed yet (now <= end).
   */
  protected function computeValue() {
    if (!$this->isCalculated) {
      $entity = $this->getEntity();
      $value = FALSE;
      $entity_bundles = ['territorial_report', 'event'];
      if ($entity instanceof NodeInterface
        && in_array($entity->bundle(), $entity_bundles)
        && $entity->hasField('field_validity_range')
        && !$entity->get('field_validity_range')->isEmpty()) {
        $now = new DrupalDateTime();
        $date_range = $entity->get('field_validity_range');
        $start_date = DrupalDateTime::createFromFormat('Y-m-d', $date_range->value);
        if ($start_date <= $now) {
          $value = TRUE;
          if (!empty($date_range->end_value)) {
            $end_date = DrupalDateTime::createFromFormat('Y-m-d', $date_range->end_value);
            $value = $now <= $end_date;
          }
        }
      }
      $this->list[0] = $this->createItem(0, $value);
      $this->isCalculated = TRUE;
    }
  }

}
