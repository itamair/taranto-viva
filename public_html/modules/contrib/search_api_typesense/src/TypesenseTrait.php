<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense;

/**
 * Common methods for Typesense integration.
 */
trait TypesenseTrait {

  /**
   * Generate a UUID.
   *
   * @return string
   *   The generated UUID.
   */
  public function generateUuid(): string {
    try {
      return \Drupal::service('uuid')->generate();
    }
    catch (\Exception $e) {
      \Drupal::logger('search_api_typesense')->error($e->getMessage());
    }

    return '';
  }

  /**
   * Check if id contains the '/' character.
   *
   * @param string $id
   *   The id to check.
   *
   * @return bool
   *   TRUE if the id is valid, FALSE otherwise.
   */
  public function checkValidId(string $id): bool {
    return !\str_contains($id, '/');
  }

}
