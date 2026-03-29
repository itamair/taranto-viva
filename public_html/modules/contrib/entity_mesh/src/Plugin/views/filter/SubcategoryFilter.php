<?php

namespace Drupal\entity_mesh\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\InOperator;

/**
 * Provides a custom filter for a specific column.
 *
 * @ViewsFilter("subcategory_filter")
 */
class SubcategoryFilter extends InOperator {

  /**
   * Value form type.
   *
   * @var string
   */
  protected $valueFormType = 'select';

  /**
   * {@inheritdoc}
   */
  protected $filterLabel = 'Subcategory';

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (empty($this->valueOptions)) {
      // Fixed values for subcategory filter.
      $this->valueOptions = [
        'access-denied-link' => 'access-denied-link',
        'broken-link' => 'broken-link',
        'iframe' => 'iframe',
        'link' => 'link',
        'redirected-link' => 'redirected-link',
        'invalid-url' => 'invalid-url',
        'invalid-tel' => 'invalid-tel',
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
