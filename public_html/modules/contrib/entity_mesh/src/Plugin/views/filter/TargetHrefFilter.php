<?php

namespace Drupal\entity_mesh\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\StringFilter;

/**
 * Provides a custom filter for a specific column.
 *
 * @ViewsFilter("target_href_filter")
 */
class TargetHrefFilter extends StringFilter {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->value = (is_array($this->value))
      ? array_map('urlencode', $this->value)
      : urlencode($this->value);
    parent::query();
  }

}
