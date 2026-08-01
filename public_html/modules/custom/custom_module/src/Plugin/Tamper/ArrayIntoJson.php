<?php

namespace Drupal\custom_module\Plugin\Tamper;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\tamper\Exception\TamperException;
use Drupal\tamper\TamperBase;
use Drupal\tamper\TamperableItemInterface;

/**
 * Plugin implementation of the implode plugin.
 *
 * @Tamper(
 *   id = "array_into_json",
 *   label = @Translation("Array into Json"),
 *   description = @Translation("Converts an Array into Json."),
 *   category = "List",
 *   handle_multiples = TRUE
 * )
 */
class ArrayIntoJson extends TamperBase {

  /**
   * {@inheritdoc}
   */
  public function tamper($data, ?TamperableItemInterface $item = NULL) {
    // Don't process null values.
    if (is_null($data)) {
      return NULL;
    }

    if (is_string($data)) {
      return $data;
    }

    if (!is_array($data)) {
      throw new TamperException('Input should be an array.');
    }

    return JSON::encode($data);
  }

}
