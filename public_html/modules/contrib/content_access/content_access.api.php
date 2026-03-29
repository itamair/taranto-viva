<?php

/**
 * @file
 * API documentation for Content Access module.
 */

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Implement this hook to alter access to node page settings.
 *
 * For example, if you use ACL module, you could want to allow access to this
 * page to people you allowed to edit.
 *
 * @param Drupal\Core\Session\AccountInterface $account
 *   The user object on which the permissions are checked.
 * @param Drupal\Core\Routing\RouteMatchInterface $route_match
 *   The route match on which the permissions are checked.
 * @param \Drupal\Core\Access\AccessResultInterface $access
 *   Access result before processing hook implementations.
 */
function hook_content_access_node_page(AccountInterface $account, RouteMatchInterface $route_match, array $access_parameters, AccessResultInterface $access): AccessResultInterface {
  return AccessResult::forbidden();
}
