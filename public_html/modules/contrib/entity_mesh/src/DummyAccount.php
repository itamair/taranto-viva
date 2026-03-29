<?php

namespace Drupal\entity_mesh;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\UserSession;

/**
 * Dummy account class for check access to entities base on roles.
 */
final class DummyAccount extends UserSession implements DummyAccountInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|null
   */
  protected $entityTypeManager;

  /**
   * Creates a DummyAccount object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   *
   * @return static
   *   A new DummyAccount instance.
   */
  public static function create(EntityTypeManagerInterface $entity_type_manager) {
    $instance = new static();
    $instance->entityTypeManager = $entity_type_manager;
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function setId($uid) {
    $this->uid = $uid;
  }

  /**
   * {@inheritdoc}
   */
  public function setAsAuthenticated() {
    $this->roles = [DummyAccountInterface::AUTHENTICATED_ROLE];
  }

  /**
   * {@inheritdoc}
   */
  public function setAsAnonymous() {
    $this->roles = [DummyAccountInterface::ANONYMOUS_ROLE];
  }

  /**
   * {@inheritdoc}
   */
  public function setRole(string $role) {
    $this->roles[] = $role;
  }

  /**
   * {@inheritdoc}
   */
  public function setRoles(array $roles) {
    $this->roles = $roles;
  }

  /**
   * {@inheritdoc}
   */
  public function setPreferredLangcode($langcode) {
    $this->preferred_langcode = $langcode;
  }

  /**
   * {@inheritdoc}
   */
  public function isAuthenticated() {
    return in_array(DummyAccountInterface::AUTHENTICATED_ROLE, $this->roles);
  }

  /**
   * {@inheritdoc}
   */
  public function isAnonymous() {
    return in_array(DummyAccountInterface::ANONYMOUS_ROLE, $this->roles);
  }

  /**
   * {@inheritdoc}
   */
  public function getAccountName() {
    // Default account name.
    return 'dummy-account';
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayName() {
    // Default display name.
    return 'Dummy Account';
  }

  /**
   * {@inheritdoc}
   */
  public function getEmail() {
    // Default empty email.
    return "dummy-account@dummyaccount.com";
  }

  /**
   * {@inheritdoc}
   */
  public function hasPermission($permission) {
    // Check if any of the roles have the permission.
    $role_storage = $this->entityTypeManager->getStorage('user_role');
    foreach ($this->roles as $role_id) {
      $role = $role_storage->load($role_id);
      if ($role && $role->hasPermission($permission)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeZone() {
    // Default timezone.
    return 'UTC';
  }

  /**
   * {@inheritdoc}
   */
  public function getLastAccessedTime() {
    // Default last accessed time.
    return 0;
  }

}
