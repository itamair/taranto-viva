<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for search_api_typesense_test.
 */
class SearchApiTypesenseTestHooks {

  /**
   * Implements hook_search_api_backend_info_alter().
   *
   * @param array<string, mixed> $backend_info
   *   The backend information array.
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('search_api_backend_info_alter')]
  public function backendInfoAlter(array &$backend_info): void {
    if (isset($backend_info['search_api_typesense'])) {
      $backend_info['search_api_typesense']['class'] = 'Drupal\search_api_typesense_test\Plugin\search_api\backend\TestTypesenseBackend';
    }
  }

}
