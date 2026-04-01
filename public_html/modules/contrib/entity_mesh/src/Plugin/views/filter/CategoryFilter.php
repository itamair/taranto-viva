<?php

namespace Drupal\entity_mesh\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\InOperator;

/**
 * Provides a custom filter for a specific column.
 *
 * @ViewsFilter("category_filter")
 */
class CategoryFilter extends InOperator {

  /**
   * Value form type.
   *
   * @var string
   */
  protected $valueFormType = 'select';

  /**
   * {@inheritdoc}
   */
  protected $filterLabel = 'Category';

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (empty($this->valueOptions)) {
      // Fixed values for category filter.
      $this->valueOptions = [
        'link' => 'link',
        'iframe' => 'iframe',
      ];
    }
    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Make sure that the entity base table is in the query.
    $this->ensureMyTable();
    parent::query();
  }

}
