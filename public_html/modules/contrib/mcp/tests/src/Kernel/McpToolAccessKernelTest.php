<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp\Plugin\McpPluginManager;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests tool-level access control for MCP plugins.
 *
 * @group mcp
 */
class McpToolAccessKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'key',
    'jsonrpc',
    'mcp',
  ];

  /**
   * The MCP plugin manager.
   *
   * @var \Drupal\mcp\Plugin\McpPluginManager
   */
  protected McpPluginManager $pluginManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('user_role');
    $this->installConfig(['user', 'mcp']);

    $this->pluginManager = $this->container->get('plugin.manager.mcp');

    // Create test roles.
    Role::create([
      'id' => 'test_role_1',
      'label' => 'Test Role 1',
    ])->save();

    Role::create([
      'id' => 'test_role_2',
      'label' => 'Test Role 2',
    ])->save();
  }

  /**
   * Tests admin permission override for tools.
   */
  public function testAdminPermissionOverrideForTools(): void {
    $config = $this->config('mcp.settings');

    // Configure plugin with restricted tool access.
    $config->set('plugins.general', [
      'enabled' => TRUE,
      'roles' => ['test_role_1'],
      'config' => [],
      'tools' => [
        'get_file' => [
          'enabled' => TRUE,
          'roles' => ['test_role_2'],
          'display_name' => '',
          'description' => '',
        ],
      ],
    ])->save();

    // Create admin user.
    $adminRole = Role::create([
      'id' => 'admin_role',
      'label' => 'Admin Role',
    ]);
    $adminRole->grantPermission('administer mcp configuration');
    $adminRole->save();

    $adminUser = User::create([
      'name' => 'admin',
      'roles' => ['admin_role'],
    ]);
    $adminUser->save();

    // Create regular user without required role.
    $regularUser = User::create([
      'name' => 'regular',
      'roles' => ['authenticated'],
    ]);
    $regularUser->save();

    $plugin = $this->pluginManager->createInstance('general');

    // Test admin access (should override role restrictions).
    $this->container->get('current_user')->setAccount($adminUser);
    $access = $plugin->hasToolAccess('get_file');
    $this->assertTrue($access->isAllowed(), 'Admin should have access to all tools');

    // Test regular user access (should be denied).
    $this->container->get('current_user')->setAccount($regularUser);
    $access = $plugin->hasToolAccess('get_file');
    $this->assertFalse($access->isAllowed(), 'Regular user without required role should not have access');
  }

  /**
   * Tests that disabled tools cannot be accessed.
   */
  public function testDisabledToolsCannotBeAccessed(): void {
    $config = $this->config('mcp.settings');

    // Configure plugin with a disabled tool.
    $config->set('plugins.general', [
      'enabled' => TRUE,
      'roles' => ['authenticated'],
      'config' => [],
      'tools' => [
        'get_file' => [
          'enabled' => FALSE,
          'roles' => [],
          'display_name' => '',
          'description' => '',
        ],
      ],
    ])->save();

    // Create user with all permissions.
    $adminRole = Role::create([
      'id' => 'admin_role',
      'label' => 'Admin Role',
    ]);
    $adminRole->grantPermission('administer mcp configuration');
    $adminRole->save();

    $user = User::create([
      'name' => 'admin',
      'roles' => ['admin_role'],
    ]);
    $user->save();

    $plugin = $this->pluginManager->createInstance('general');
    $this->container->get('current_user')->setAccount($user);

    // Test that disabled tool cannot be accessed even by admin.
    $access = $plugin->hasToolAccess('get_file');
    $this->assertFalse($access->isAllowed(), 'Disabled tool should not be accessible even by admin');
    $this->assertEquals('Tool is disabled.', $access->getReason());
  }

  /**
   * Tests role inheritance from plugin to tools.
   */
  public function testRoleInheritanceFromPluginToTools(): void {
    $config = $this->config('mcp.settings');

    // Configure plugin with roles but no tool-specific roles.
    $config->set('plugins.general', [
      'enabled' => TRUE,
      'roles' => ['test_role_1'],
      'config' => [],
      'tools' => [
        'get_file' => [
          'enabled' => TRUE,
    // Empty means inherit from plugin.
          'roles' => [],
          'display_name' => '',
          'description' => '',
        ],
      ],
    ])->save();

    // Create users with different roles.
    $userWithRole = User::create([
      'name' => 'user_with_role',
      'roles' => ['test_role_1'],
    ]);
    $userWithRole->save();

    $userWithoutRole = User::create([
      'name' => 'user_without_role',
      'roles' => ['authenticated'],
    ]);
    $userWithoutRole->save();

    $plugin = $this->pluginManager->createInstance('general');

    // User with plugin role should have access.
    $this->container->get('current_user')->setAccount($userWithRole);
    $access = $plugin->hasToolAccess('get_file');
    $this->assertTrue($access->isAllowed(), 'User with plugin role should have tool access when tool has no specific roles');

    // User without plugin role should not have access.
    $this->container->get('current_user')->setAccount($userWithoutRole);
    $access = $plugin->hasToolAccess('get_file');
    $this->assertFalse($access->isAllowed(), 'User without plugin role should not have tool access');
  }

  /**
   * Tests tool access with empty roles configuration.
   */
  public function testToolAccessWithEmptyRoles(): void {
    $config = $this->config('mcp.settings');

    // Configure plugin with no role restrictions.
    $config->set('plugins.general', [
      'enabled' => TRUE,
    // No plugin-level role restrictions.
      'roles' => [],
      'config' => [],
      'tools' => [
        'get_file' => [
          'enabled' => TRUE,
    // No tool-level role restrictions.
          'roles' => [],
          'display_name' => '',
          'description' => '',
        ],
      ],
    ])->save();

    // Create user with permission to use MCP server.
    $role = Role::create([
      'id' => 'mcp_user',
      'label' => 'MCP User',
    ]);
    $role->grantPermission('use mcp server');
    $role->save();

    $user = User::create([
      'name' => 'user',
      'roles' => ['mcp_user'],
    ]);
    $user->save();

    $plugin = $this->pluginManager->createInstance('general');
    $this->container->get('current_user')->setAccount($user);

    $access = $plugin->hasToolAccess('get_file');
    $this->assertTrue($access->isAllowed(), 'User with use mcp server permission should have access when no role restrictions');
  }

}
