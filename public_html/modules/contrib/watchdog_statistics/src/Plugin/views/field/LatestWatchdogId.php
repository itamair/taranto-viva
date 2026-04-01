<?php

namespace Drupal\watchdog_statistics\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;

/**
 * Shows the latest watchdog id.
 *
 * @ViewsField("latest_watchdog_id")
 */
class LatestWatchdogId extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->realField = 'wid';
    $this->ensureMyTable();
    // Add the field.
    $params = ['function' => 'max'];
    $this->field_alias = $this->query->addField($this->tableAlias, $this->realField, NULL, $params);

    $this->addAdditionalFields();
  }

}
