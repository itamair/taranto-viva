<?php

namespace Drupal\entity_mesh\Plugin\views\filter;

/**
 * Provides a custom filter for a specific column.
 *
 * @ViewsFilter("source_bundle_filter")
 */
class SourceBundleFilter extends BaseSelectFilter {

  /**
   * {@inheritdoc}
   */
  protected $tableColumn = 'source_entity_bundle';

  /**
   * {@inheritdoc}
   */
  protected $databaseTable = 'entity_mesh';

  /**
   * {@inheritdoc}
   */
  protected $filterLabel = 'Source Entity Bundle';

}
