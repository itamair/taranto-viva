<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Api;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines a class for unauthorized request exceptions.
 */
class SearchApiTypesenseRequestUnauthorizedException extends SearchApiTypesenseException {

  use StringTranslationTrait;

  /**
   * SearchApiTypesenseRequestUnauthorizedException constructor.
   *
   * @param string $requiredAction
   *   The required action for the API key.
   */
  public function __construct(string $requiredAction) {
    $message = $this->t(
      'The API key is unauthorized for the required action: "@action". Please ensure the API key has the necessary permissions.',
      ['@action' => $requiredAction],
    )->render();

    parent::__construct($message);
  }

}
