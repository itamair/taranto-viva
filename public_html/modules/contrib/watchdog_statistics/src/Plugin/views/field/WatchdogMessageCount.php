<?php

namespace Drupal\watchdog_statistics\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;

/**
 * Allow to show the counts of a specific message in a watchdog view.
 *
 * @ViewsField("watchdog_message_count")
 */
class WatchdogMessageCount extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->realField = 'wid';
    $this->ensureMyTable();
    // Add the field.
    $params = ['function' => 'count'];
    $this->field_alias = $this->query->addField($this->tableAlias, $this->realField, NULL, $params);

    $this->addAdditionalFields();

    $this->query->addTag('watchdog_message_count');
  }

}
