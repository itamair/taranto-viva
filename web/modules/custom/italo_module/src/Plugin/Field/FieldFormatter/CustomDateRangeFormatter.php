<?php

namespace Drupal\italo_module\Plugin\Field\FieldFormatter;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\datetime\Plugin\Field\FieldFormatter\DateTimeCustomFormatter;
use Drupal\datetime_range\DateTimeRangeTrait;

/**
 * Plugin implementation of the 'Custom' formatter for 'daterange' fields.
 *
 * This formatter renders the data range as plain text, with a fully
 * configurable date format using the PHP date syntax and separator.
 *
 * @FieldFormatter(
 *   id = "custom_daterange",
 *   label = @Translation("Custom DateRange"),
 *   field_types = {
 *     "daterange"
 *   }
 * )
 */
class CustomDateRangeFormatter extends DateTimeCustomFormatter {

  use DateTimeRangeTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'separator' => '-',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    // @todo Evaluate removing this method in
    // https://www.drupal.org/node/2793143 to determine if the behavior and
    // markup in the base class implementation can be used instead.
    $elements = [];
    $separator = $this->getSetting('separator');

    foreach ($items as $delta => $item) {
      /** @var \Drupal\Core\Datetime\DrupalDateTime $start_date */
      $start_date = $item->start_date;
      if (!empty($item->start_date) && !empty($item->end_date)) {
        /** @var \Drupal\Core\Datetime\DrupalDateTime $end_date */
        $end_date = $item->end_date;

        if ($start_date->getTimestamp() !== $end_date->getTimestamp()) {
          $elements[$delta] = [
            'start_date' => $this->buildDate($start_date),
            'separator' => ['#plain_text' => ' ' . $separator . ' '],
            'end_date' => $this->buildDate($end_date),
          ];
        }
        else {
          $elements[$delta] = $this->buildDate($start_date);
        }
      }
      else {
        $elements[$delta] = [
          'start_date' => $this->buildDate($start_date),
          'separator' => ['#plain_text' => ' ' . $separator . ' ' . $this->t('active')],
        ];
      }
    }

    return $elements;
  }

  /**
   * Creates a render array from a date object.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $date
   *   A date object.
   *
   * @return array
   *   A render array.
   */
  protected function buildDate(DrupalDateTime $date): array {
    $this->setTimeZone($date);

    return [
      '#markup' => $this->formatDate($date),
      '#cache' => [
        'contexts' => [
          'timezone',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);

    $form['separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Date separator'),
      '#description' => $this->t('The string to separate the start and end dates'),
      '#default_value' => $this->getSetting('separator'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();

    if ($separator = $this->getSetting('separator')) {
      $summary[] = $this->t('Separator: %separator', ['%separator' => $separator]);
    }

    return $summary;
  }

}
