<?php

namespace Drupal\entity_mesh\Plugin\views\filter;

/**
 * Provides a custom filter for a specific column.
 *
 * @ViewsFilter("target_schema_filter")
 */
class TargetSchemaFilter extends BaseSelectFilter {

  /**
   * {@inheritdoc}
   */
  protected $tableColumn = 'target_scheme';

  /**
   * {@inheritdoc}
   */
  protected $databaseTable = 'entity_mesh';

  /**
   * {@inheritdoc}
   */
  protected $filterLabel = 'Target Schema';

}
