<?php

declare(strict_types=1);

namespace Drupal\Tests\jsonrpc_discovery\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Drupal\user\UserInterface;

/**
 * This class provides methods specifically for testing something.
 *
 * @group jsonrpc
 */
abstract class JsonRpcDiscoveryFunctionalTestBase extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'basic_auth',
    'jsonrpc',
    'jsonrpc_core',
    'jsonrpc_discovery',
  ];

  /**
   * A user with authenticated permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $user;

  /**
   * A user with admin permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Grant authorized users permission to use JSON-RPC.
    $auth_role = Role::load(RoleInterface::AUTHENTICATED_ID);
    $this->grantPermissions($auth_role, ['use jsonrpc services']);

    $this->user = $this->drupalCreateUser([], 'user', FALSE, ['mail' => 'user@example.com']);

    $this->adminUser = $this->drupalCreateUser([], 'adminUser', TRUE, ['mail' => 'admin@example.com']);

    $this->adminUser->save();
  }

}
