<?php

namespace Drupal\italo_module;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\node\NodeInterface;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Generates a TerritorialReportActiveLevelFieldItemList.
 */
class ActiveLevelFieldItemList extends FieldItemList {

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
   * Generate the Active Level Value for specific Content Types.
   */
  protected function computeValue(): void {
    if (!$this->isCalculated) {
      $entity = $this->getEntity();
      $value = '';
      $entity_bundles = ['territorial_report'];
      if ($entity instanceof NodeInterface && in_array($entity->bundle(), $entity_bundles)) {
        $now = new DrupalDateTime();
        if (is_array($entity->get('field_date_range')->getValue())) {
          $start_date = DrupalDateTime::createFromFormat('Y-m-d', $entity->get('field_date_range')->value);
          if ($end_date = $entity->get('field_date_range')->end_value) {
            $end_date = DrupalDateTime::createFromFormat('Y-m-d', $end_date);
            if ($start_date < $now && $now < $end_date) {
              $value = is_array($entity->get('field_active_type')->getValue()) ? $entity->field_active_type->value : '';
            }
          }
          elseif ($start_date < $now) {
            $value = is_array($entity->get('field_active_type')->getValue()) ? $entity->field_active_type->value : '';
          }
        };
      }
      $this->list[0] = $this->createItem(0, $value);
      $this->isCalculated = TRUE;
    }
  }

}
