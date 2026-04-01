<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\search_api\ServerInterface;
use Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend;

/**
 * Checks access for displaying Typesense configuration generator actions.
 */
class ServerLocalActionAccessCheck implements AccessInterface {

  /**
   * A custom access check.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\search_api\ServerInterface|null $search_api_server
   *   (optional) The Search API server entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function access(
    AccountInterface $account,
    ?ServerInterface $search_api_server = NULL,
  ): AccessResultInterface {
    if ($search_api_server !== NULL && $search_api_server->getBackend() instanceof SearchApiTypesenseBackend) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

}
