<?php

namespace Drupal\watchdog_statistics\Plugin\views\sort;

use Drupal\views\Plugin\views\sort\Standard;

/**
 * Sort by count of messages.
 *
 * @ViewsSort("watchdog_message_count")
 */
class WatchdogMessageCount extends Standard {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    $this->query->addOrderBy(NULL, '(COUNT(watchdog.wid))', $this->options['order'], 'watchdog_message_count');
  }

}
