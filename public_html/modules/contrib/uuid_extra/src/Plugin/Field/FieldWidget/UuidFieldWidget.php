<?php

namespace Drupal\uuid_extra\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'uuid' widget.
 *
 * @FieldWidget(
 *   id = "uuid",
 *   label = @Translation("UUID"),
 *   field_types = {
 *     "uuid"
 *   }
 * )
 */
class UuidFieldWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(
    FieldItemListInterface $items,
    $delta,
    array $element,
    array &$form,
    FormStateInterface $form_state
  ) {
    $element['value'] = $element + [
      '#type' => 'textfield',
      '#disabled' => TRUE,
      '#default_value' => $items[$delta]->value ?? NULL,
    ];
    return $element;
  }

}
