<?php

namespace Drupal\entity_mesh\Plugin\views\filter;

/**
 * Provides a custom filter for a specific column.
 *
 * @ViewsFilter("target_type_link_filter")
 */
class TargeTypeLinkFilter extends BaseSelectFilter {

  /**
   * {@inheritdoc}
   */
  protected $tableColumn = 'target_link_type';

  /**
   * {@inheritdoc}
   */
  protected $databaseTable = 'entity_mesh';

  /**
   * {@inheritdoc}
   */
  protected $filterLabel = 'Target Type Link';

}
