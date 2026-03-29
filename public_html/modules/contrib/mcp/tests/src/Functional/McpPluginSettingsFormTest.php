<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\UserInterface;

/**
 * Tests the MCP plugin settings form functionality.
 *
 * @group mcp
 */
class McpPluginSettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['mcp', 'node'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with the 'administer mcp configuration' permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'administer site configuration',
      'administer mcp configuration',
    ]);

    // Create additional test roles.
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
   * Tests that the plugin settings form renders correctly.
   */
  public function testPluginSettingsFormRendersCorrectly(): void {
    $this->drupalLogin($this->adminUser);

    // Navigate to the general plugin settings.
    $this->drupalGet('/admin/config/mcp/plugins/general/settings');
    $this->assertSession()->statusCodeEquals(200);

    // Check for plugin settings fieldset.
    $this->assertSession()->elementExists('css', '#edit-plugin-settings');
    $this->assertSession()->fieldExists('plugin_settings[enabled]');
    // Checkboxes have individual fields per role.
    $this->assertSession()->fieldExists('plugin_settings[roles][authenticated]');

    // Check for tools settings section.
    $this->assertSession()->elementExists('css', '#edit-tools-settings');
    $this->assertSession()->pageTextContains('Tool Settings');
    $this->assertSession()->pageTextContains('Configure individual tools provided by this plugin.');
  }

  /**
   * Tests saving plugin enabled/disabled state.
   */
  public function testSavingPluginEnabledDisabledState(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/mcp/plugins/general/settings');

    // Initially, the plugin should be enabled.
    $this->assertSession()->checkboxChecked('plugin_settings[enabled]');

    // Disable the plugin.
    $this->submitForm([
      'plugin_settings[enabled]' => FALSE,
    ], 'Save configuration');

    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Verify the plugin is disabled in config.
    $config = $this->config('mcp.settings');
    $this->assertFalse($config->get('plugins.general.enabled'));

    // Reload the form and verify the checkbox state.
    $this->drupalGet('/admin/config/mcp/plugins/general/settings');
    $this->assertSession()->checkboxNotChecked('plugin_settings[enabled]');

    // Re-enable the plugin.
    $this->submitForm([
      'plugin_settings[enabled]' => TRUE,
    ], 'Save configuration');

    $this->assertTrue($this->config('mcp.settings')->get('plugins.general.enabled'));
  }

  /**
   * Tests enabling and disabling individual tools.
   */
  public function testEnableDisableTools(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/mcp/plugins/general/settings');

    // Disable a specific tool.
    $this->submitForm([
      'plugin_settings[enabled]' => TRUE,
      'tools_settings[info][enabled]' => FALSE,
    ], 'Save configuration');

    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Verify tool configuration in config.
    $config = $this->config('mcp.settings');
    $this->assertFalse($config->get('plugins.general.tools.info.enabled'));

    // Reload and verify form values.
    $this->drupalGet('/admin/config/mcp/plugins/general/settings');
    $this->assertSession()->checkboxNotChecked('tools_settings[info][enabled]');
  }

  /**
   * Tests custom descriptions for tools.
   */
  public function testCustomDisplayNames(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/mcp/plugins/general/settings');

    // Set custom description for tools.
    $this->submitForm([
      'plugin_settings[enabled]' => TRUE,
      'tools_settings[info][enabled]' => TRUE,
      'tools_settings[info][custom_description]' => 'Custom tool description for site information',
    ], 'Save configuration');

    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Verify custom description in config.
    $config = $this->config('mcp.settings');
    $this->assertEquals('Custom tool description for site information', $config->get('plugins.general.tools.info.description'));

    // Reload and verify form values.
    $this->drupalGet('/admin/config/mcp/plugins/general/settings');
    $this->assertSession()->fieldValueEquals('tools_settings[info][custom_description]', 'Custom tool description for site information');

    // Verify the machine name is displayed (read-only).
    $this->assertSession()->pageTextContains('general_info');
  }

  /**
   * Tests custom descriptions for tools.
   */
  public function testCustomDescriptions(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/mcp/plugins/general/settings');

    // Set custom descriptions for tools.
    $customDescription = 'Provides detailed information about the Drupal site including version and configuration.';

    $this->submitForm([
      'plugin_settings[enabled]' => TRUE,
      'tools_settings[info][enabled]' => TRUE,
      'tools_settings[info][custom_description]' => $customDescription,
    ], 'Save configuration');

    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Verify custom descriptions in config.
    $config = $this->config('mcp.settings');
    $this->assertEquals($customDescription, $config->get('plugins.general.tools.info.description'));

    // Reload and verify form values.
    $this->drupalGet('/admin/config/mcp/plugins/general/settings');
    $this->assertSession()->fieldValueEquals('tools_settings[info][custom_description]', $customDescription);
  }

  /**
   * Tests that disabled plugin disables tool settings.
   */
  public function testDisabledPluginDisablesToolSettings(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/mcp/plugins/general/settings');

    // First, disable the plugin.
    $this->submitForm([
      'plugin_settings[enabled]' => FALSE,
    ], 'Save configuration');

    // Reload the form.
    $this->drupalGet('/admin/config/mcp/plugins/general/settings');

    // Verify that tool settings fields are disabled.
    $toolEnabledField = $this->assertSession()->fieldExists('tools_settings[info][enabled]');
    $this->assertTrue($toolEnabledField->hasAttribute('disabled'), 'Tool enabled field should be disabled when plugin is disabled');

    $toolDescriptionField = $this->assertSession()->fieldExists('tools_settings[info][custom_description]');
    $this->assertTrue($toolDescriptionField->hasAttribute('disabled'), 'Tool custom description field should be disabled when plugin is disabled');

    // For checkboxes, check one of the role fields.
    $toolRolesField = $this->assertSession()->fieldExists('tools_settings[info][roles][authenticated]');
    $this->assertTrue($toolRolesField->hasAttribute('disabled'), 'Tool roles field should be disabled when plugin is disabled');
  }

}
