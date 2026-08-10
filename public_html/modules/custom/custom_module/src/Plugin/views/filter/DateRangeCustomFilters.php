<?php

namespace Drupal\custom_module\Plugin\views\filter;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\datetime\Plugin\views\filter\Date;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

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

  use StringTranslationTrait;

  /**
   * Add a type selector to the value form.
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    if (!$form_state->get('exposed')) {
      $form['value']['type'] = [
        '#type' => 'radios',
        '#title' => $this->t('Value type'),
        '#options' => [
          'date' => $this->t('A date in any machine readable format. CCYY-MM-DD HH:MM:SS is preferred.'),
          'offset' => $this->t('An offset from the current time such as "@example1" or "@example2"', [
            '@example1' => '+1 day',
            '@example2' => '-2 hours -30 minutes',
          ]
          ),
        ],
        '#default_value' => !empty($this->value['type']) ? $this->value['type'] : 'date',
      ];
    }
    parent::valueForm($form, $form_state);

    if ($form_state->get('exposed')) {
      $default_value = $this->value['value'];
      $options = [
        '-1 month' => $this->t('1 Month Ago'),
        '+1 day' => $this->t('Tomorrow'),
        '+1 week' => $this->t('In 1 Week'),
        '+1 month' => $this->t('In 1 Month'),
      ];
      if (!array_key_exists($default_value, $options)) {
        $options[$default_value] = $this->t('@default_value', [
          '@default_value' => $default_value,
        ]);
      }
      $form["value"] = [
        '#type' => 'select',
        '#title' => $this->t('Hidden Layers Controls'),
        '#description' => $this->t('Choose the Layers that will not appear in the Layers Control'),
        '#options' => $options,
        '#default_value' => $default_value,
      ];
    }

  }

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
    $operators['date_range_overlaps'] = [
      'title' => $this->t('Date Range Overlaps'),
      'method' => 'opDateRangeOverlaps',
      'short' => $this->t('dr_within'),
      'values' => 2,
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

  /**
   * Filters by operator Overlaps.
   *
   * @param object $field
   *   The views field.
   */
  protected function opDateRangeOverlaps($field) {
    $end_field = substr($field, 0, -6) . '_end_value';

    $timezone = $this->getTimezone();
    $origin_offset = $this->getOffset($this->value['min'], $timezone);

    // Although both 'min' and 'max' values are required, default empty 'min'
    // value as UNIX timestamp 0.
    $min = (!empty($this->value['min'])) ? $this->value['min'] : '@0';

    // Convert to ISO format and format for query. UTC timezone is used since
    // dates are stored in UTC.
    $a = new DateTimePlus($min, new \DateTimeZone($timezone));
    $a = $this->query->getDateFormat($this->query->getDateField("'" . $this->dateFormatter->format($a->getTimestamp() + $origin_offset, 'custom', DateTimeItemInterface::DATETIME_STORAGE_FORMAT, DateTimeItemInterface::STORAGE_TIMEZONE) . "'", TRUE, $this->calculateOffset), $this->dateFormat, TRUE);
    $b = new DateTimePlus($this->value['max'], new \DateTimeZone($timezone));
    $b = $this->query->getDateFormat($this->query->getDateField("'" . $this->dateFormatter->format($b->getTimestamp() + $origin_offset, 'custom', DateTimeItemInterface::DATETIME_STORAGE_FORMAT, DateTimeItemInterface::STORAGE_TIMEZONE) . "'", TRUE, $this->calculateOffset), $this->dateFormat, TRUE);

    $field = $this->query->getDateFormat($this->query->getDateField($field, TRUE, $this->calculateOffset), $this->dateFormat, TRUE);
    $end_field = $this->query->getDateFormat($this->query->getDateField($end_field, TRUE, $this->calculateOffset), $this->dateFormat, TRUE);
    $this->query->addWhereExpression($this->options['group'], "($a <= $end_field AND $b >= $field) OR ($a <= $field AND $b >= $field)");
  }

}
