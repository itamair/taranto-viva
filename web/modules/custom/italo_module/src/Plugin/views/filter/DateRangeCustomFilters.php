<?php

namespace Drupal\italo_module\Plugin\views\filter;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\datetime\Plugin\views\filter\Date;

/**
 * Date/time Ranges Custom Views filters.
 *
 * Extends Date filter to include Custom Date Range operations.
 * Inspired by the "views_daterange_filters" module views filters.
 *
 * @see: https://www.drupal.org/project/views_daterange_filters
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("views_custom_daterange_filters")
 */
class DateRangeCustomFilters extends Date implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   *
   * @return array
   *   Array of operators.
   */
  public function operators() {
    $operators = parent::operators();
    $operators['date_range_includes'] = [
      'title' => $this->t('Date Range Includes'),
      'method' => 'opDateRangeIncludes',
      'short' => $this->t('dr_includes'),
      'values' => 1,
    ];
    return $operators;
  }

  /**
   * Filters by operator Includes.
   *
   * @param mixed $field
   *   The field.
   */
  protected function opDateRangeIncludes($field) {
    $end_field = substr($field, 0, -6) . '_end_value';

    $timezone = $this->getTimezone();
    $origin_offset = $this->getOffset($this->value['value'], $timezone);

    // Convert to ISO. UTC timezone is used since dates are stored in UTC.
    $value = new DateTimePlus($this->value['value'], new \DateTimeZone($timezone));
    $value = $this->query->getDateFormat($this->query->getDateField("'" . $this->dateFormatter->format($value->getTimestamp() + $origin_offset, 'custom', DateTimeItemInterface::DATETIME_STORAGE_FORMAT, DateTimeItemInterface::STORAGE_TIMEZONE) . "'", TRUE, $this->calculateOffset), $this->dateFormat, TRUE);

    $field = $this->query->getDateFormat($this->query->getDateField($field, TRUE, $this->calculateOffset), $this->dateFormat, TRUE);
    $end_field = $this->query->getDateFormat($this->query->getDateField($end_field, TRUE, $this->calculateOffset), $this->dateFormat, TRUE);

    $this->query->addWhereExpression($this->options['group'], "($end_field IS NULL AND $value >= $field) OR $value BETWEEN $field AND $end_field");
  }

}
