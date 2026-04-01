<?php

namespace Drupal\watchdog_statistics\Plugin\views\sort;

use Drupal\views\Plugin\views\sort\Date;

/**
 * Sort by the latest watchdog date.
 *
 * @ViewsSort("latest_watchdog_timestamp")
 */
class LatestWatchdogTimestamp extends Date {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    $this->query->addOrderBy(NULL, '(MAX(watchdog.timestamp))', $this->options['order'], 'latest_watchdog_timestamp');
  }

}
