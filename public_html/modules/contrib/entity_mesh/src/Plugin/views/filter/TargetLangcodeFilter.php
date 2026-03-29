<?php

namespace Drupal\entity_mesh\Plugin\views\filter;

/**
 * Provides a custom filter for a specific column.
 *
 * @ViewsFilter("target_langcode_filter")
 */
class TargetLangcodeFilter extends BaseSelectFilter {

  /**
   * {@inheritdoc}
   */
  protected $tableColumn = 'target_entity_langcode';

  /**
   * {@inheritdoc}
   */
  protected $databaseTable = 'entity_mesh';

  /**
   * {@inheritdoc}
   */
  protected $filterLabel = 'Target Langcode';

}
