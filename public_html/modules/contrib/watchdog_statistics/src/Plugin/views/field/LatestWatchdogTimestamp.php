<?php

namespace Drupal\watchdog_statistics\Plugin\views\field;

use Drupal\views\Plugin\views\field\Date;

/**
 * Shows the latest watchdog date.
 *
 * @ViewsField("latest_watchdog_timestamp")
 */
class LatestWatchdogTimestamp extends Date {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->realField = 'timestamp';
    $this->ensureMyTable();
    // Add the field.
    $params = ['function' => 'max'];
    $this->field_alias = $this->query->addField($this->tableAlias, $this->realField, NULL, $params);

    $this->addAdditionalFields();
  }

}
