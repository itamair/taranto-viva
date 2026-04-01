<?php

namespace Drupal\entity_mesh;

use Drupal\Core\Session\AccountInterface;

/**
 * Dummy account class for check access to entities base on roles.
 */
interface DummyAccountInterface extends AccountInterface {

  /**
   * Set the user ID.
   *
   * @param int $uid
   *   User ID.
   */
  public function setId($uid);

  /**
   * Set the dummy account as authenticated.
   */
  public function setAsAuthenticated();

  /**
   * Set the dummy account as anonymous.
   */
  public function setAsAnonymous();

  /**
   * Set role for the dummy account.
   *
   * @param string $role
   *   Role ID.
   */
  public function setRole(string $role);

  /**
   * Set roles for the dummy account.
   *
   * @param array $roles
   *   Array of role IDs.
   */
  public function setRoles(array $roles);

  /**
   * Set preferred language code.
   *
   * @param int $langcode
   *   Lang-code.
   */
  public function setPreferredLangcode($langcode);

}
