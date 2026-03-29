<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Error;

/**
 * Helper trait for logging.
 */
trait LoggerTrait {

  use StringTranslationTrait;

  /**
   * Log an error to the logger and display a message.
   *
   * @param \Throwable $exception
   *   The exception.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $message
   *   The message.
   */
  public function logError(\Throwable $exception, TranslatableMarkup $message): void {
    $formatted_message = \sprintf(
      '%s. %s: %s',
      $message,
      $this->t('Error'),
      $exception->getMessage(),
    );

    \Drupal::service('logger.channel.search_api_typesense')->error($formatted_message, [
      'error' => Error::renderExceptionSafe($exception),
    ]);

    \Drupal::messenger()->addError($formatted_message);
  }

}
