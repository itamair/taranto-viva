<?php

namespace Drupal\entity_mesh\Plugin\views\filter;

/**
 * Provides a custom filter for a specific column.
 *
 * @ViewsFilter("target_bundle_filter")
 */
class TargetBundleFilter extends BaseSelectFilter {

  /**
   * {@inheritdoc}
   */
  protected $tableColumn = 'target_entity_bundle';

  /**
   * {@inheritdoc}
   */
  protected $databaseTable = 'entity_mesh';

  /**
   * {@inheritdoc}
   */
  protected $filterLabel = 'Target Entity Bundle';

}
