<?php

namespace Drupal\italo_module\Plugin\Tamper;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\tamper\Exception\TamperException;
use Drupal\tamper\TamperBase;
use Drupal\tamper\TamperableItemInterface;

/**
 * Plugin implementation of the Random Value plugin.
 *
 * @Tamper(
 *   id = "random_value_10_100",
 *   label = @Translation("Random Value from 10 to 100"),
 *   description = @Translation("Generates a random value between 10 and 100."),
 *   category = "Number",
 *   handle_multiples = TRUE
 * )
 */
class RandomValue10and100 extends TamperBase {

  /**
   * {@inheritdoc}
   */
  public function tamper($data, ?TamperableItemInterface $item = NULL) {
    return (int) rand(10, 100);
  }

}
