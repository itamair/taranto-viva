<?php

namespace Drupal\italo_module\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Date Range Active Status Filter.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("date_range_active_status_filter")
 */
class DateRangeActiveStatusFilter extends FilterPluginBase {

  /**
   * Exposed filter options.
   *
   * @var bool
   */
  protected $alwaysMultiple = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['value'] = [
      'default' => [
        'status' => 'all',
      ],
    ];
    return $options;
  }

  /**
   * {@inheritdoc}
   *
   * This may not be needed, as we don't have more than one operator. But it
   * is a pattern seen in other filters, 'opStateIs' would be a method that
   * a parent class calls during the query() method.
   */
  public function operators(): void {
    $operators = [
      'is' => [
        'title' => $this->t('The event is'),
        'method' => 'opStateIs',
        'short' => $this->t('Is'),
        'values' => 1,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function buildValueWrapper(&$form, $wrapper_identifier) {
    // If both the field and the operator are exposed, this will end up being
    // called twice. We don't want to wipe out what's already there, so if it
    // exists already, do nothing.
  }

  /**
   * The form that is show (including the exposed form).
   */
  protected function valueForm(&$form, FormStateInterface $form_state): void {
    $exposed_info = $this->exposedInfo();
    $form['value'] = [
      '#tree' => TRUE,
      'status' => [
        '#type' => 'select',
        '#options' => [
          'all' => $this->t('All'),
          'active' => $this->t('Active'),
          'inactive' => $this->t('Inactive'),
        ],
        '#default_value' => !empty($this->value['status']) ? $this->value['status'] : 'all',
        '#title' => $exposed_info['label'],
        '#description' => $exposed_info['description'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   *
   * Applying query filter. If you turn on views query debugging you should see
   * these clauses applied. If the filter is optional, and nothing is selected,
   * this code will never be called.
   */
  public function query(): void {
    $this->ensureMyTable();
    $start_field_name = "$this->tableAlias.$this->realField";
    $end_field_name = substr($start_field_name, 0, -6) . '_end_value';

    // Prepare sql clauses for each field.
    $date_start = $this->query->getDateFormat($this->query->getDateField($start_field_name, TRUE), 'Y-m-d H:i:s');
    $date_end = $this->query->getDateFormat($this->query->getDateField($end_field_name, TRUE), 'Y-m-d H:i:s');
    $date_now = $this->query->getDateFormat('FROM_UNIXTIME(***CURRENT_TIME***)', 'Y-m-d H:i:s');

    switch ($this->value['status']) {
      case 'active':
        $this->query->addWhereExpression($this->options['group'], "($date_end IS NOT NULL AND $date_now BETWEEN $date_start AND $date_end)
        OR
         ($date_end IS NULL AND $date_now >= $date_start)");
        break;

      case 'inactive':
        $this->query->addWhereExpression($this->options['group'], "$date_now > $date_end");
        break;
    }
  }

  /**
   * Admin summary makes it nice for editors.
   */
  public function adminSummary() {

    if ($this->isAGroup()) {
      return $this->t('grouped');
    }
    if (!empty($this->options['exposed'])) {
      return $this->t('exposed') . ', ' . $this->t('Default Status') . ': ' . $this->value['status'];
    }
    else {
      return $this->t('Status') . ': ' . $this->value['status'];
    }
  }

}
