<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend;

/**
 * Checks access for displaying Typesense configuration generator actions.
 */
class IndexLocalActionAccessCheck implements AccessInterface {

  /**
   * A custom access check.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\search_api\IndexInterface|null $search_api_index
   *   (optional) The Search API index entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function access(
    AccountInterface $account,
    ?IndexInterface $search_api_index = NULL,
  ): AccessResultInterface {
    if ($search_api_index !== NULL && $search_api_index->getServerInstance()
        ?->getBackend() instanceof SearchApiTypesenseBackend) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

}
