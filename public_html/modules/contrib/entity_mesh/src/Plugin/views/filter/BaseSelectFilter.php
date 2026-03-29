<?php

namespace Drupal\entity_mesh\Plugin\views\filter;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Plugin\views\filter\InOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base select filter.
 */
abstract class BaseSelectFilter extends InOperator implements ContainerFactoryPluginInterface {

  /**
   * Value form type.
   *
   * @var string
   */
  protected $valueFormType = 'select';

  /**
   * The column to filter.
   *
   * @var string
   */
  protected $tableColumn;

  /**
   * The database table used in the query to get options.
   *
   * @var string
   */
  protected $databaseTable;

  /**
   * The database column used in the query to get options.
   *
   * @var string
   */
  protected $filterLabel;

  /**
   * The database column used in the query to get options.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin_object = new static($configuration, $plugin_id, $plugin_definition);
    $plugin_object->database = $container->get('database');
    $plugin_object->realField = $plugin_object->tableColumn;
    return $plugin_object;
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (empty($this->valueOptions)) {
      $query = $this->database->select($this->databaseTable, 'm');
      $query->addField('m', $this->tableColumn, 'filter_column');
      $query->distinct();
      $query->orderBy($this->tableColumn, 'ASC');
      $query_result = $query->execute();
      if ($query_result === NULL) {
        return [];
      }
      $this->valueOptions = [];
      foreach ($query_result->fetchAll() as $row) {
        if (empty($row->filter_column)) {
          continue;
        }
        $this->valueOptions[$row->filter_column] = $row->filter_column;
      }
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
