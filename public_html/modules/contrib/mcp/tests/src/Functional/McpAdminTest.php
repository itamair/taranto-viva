<?php

namespace Drupal\Tests\mcp\Functional;

use Drupal\user\UserInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * Test Admin interface for the MCP module.
 *
 * @group mcp
 */
class McpAdminTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['mcp'];

  /**
   * A user with the 'administer site configuration' permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $user;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->user = $this->drupalCreateUser(
      [
        'administer site configuration',
        'access content',
        'administer mcp configuration',
      ]
    );
  }

  /**
   * Tests the MCP configuration page.
   */
  public function testMcpConfigPageExists() {
    $this->drupalLogin($this->user);

    // Go to the MCP configuration page.
    $this->drupalGet('/admin/config/mcp');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests the MCP configuration form.
   */
  public function testConfigFormSse() {
    $this->drupalLogin($this->user);

    // Go to the MCP configuration page.
    $this->drupalGet('/admin/config/mcp');
    $this->assertSession()->statusCodeEquals(200);

    // The form 'enable_sse' should be a checkbox with a default value of TRUE.
    $this->assertSession()->checkboxNotChecked('enable_auth');

    // Unchecked the checkbox and submit the form.
    $this->submitForm(
      [
        'enable_auth'       => TRUE,
        'auth_settings[enable_basic_auth]' => TRUE,
      ],
      'Save configuration'
    );

    // Check if configuration is saved.
    $this->drupalGet('/admin/config/mcp');
    $this->assertSession()->checkboxChecked('enable_auth');
    $this->assertSession()->checkboxChecked('auth_settings[enable_basic_auth]');
  }

  /**
   * Tests the MCP configuration form.
   */
  public function testConfigFormPluginConfig() {
    $this->drupalLogin($this->user);

    // First, go to the plugins list page.
    $this->drupalGet('/admin/config/mcp/plugins');
    $this->assertSession()->statusCodeEquals(200);

    // Check that the general plugin is listed.
    $this->assertSession()->pageTextContains('General MCP');

    // Navigate to the general plugin settings.
    $this->drupalGet('/admin/config/mcp/plugins/general/settings');
    $this->assertSession()->statusCodeEquals(200);

    // The form should have a container for plugin settings.
    $this->assertSession()->elementExists('css', '#edit-plugin-settings');

    // Check that the plugin is enabled by default.
    $this->assertSession()->checkboxChecked('plugin_settings[enabled]');

    // Check that roles field exists.
    $this->assertSession()->fieldExists('plugin_settings[roles][authenticated]');

    // Now install node module to test the content plugin.
    $this->container->get('module_installer')->install(['node']);

    // Create content types.
    $this->drupalCreateContentType(['type' => 'page']);
    $this->drupalCreateContentType(['type' => 'article']);

    // Go to the content plugin settings page.
    $this->drupalGet('/admin/config/mcp/plugins/content/settings');
    $this->assertSession()->statusCodeEquals(200);

    // Check that the plugin settings form exists.
    $this->assertSession()->elementExists('css', '#edit-plugin-settings');
    $this->assertSession()->checkboxChecked('plugin_settings[enabled]');

    // Check content type settings.
    $this->assertSession()->checkboxNotChecked(
      'plugin_settings[config][content_types][article]'
    );
    $this->assertSession()->checkboxNotChecked(
      'plugin_settings[config][content_types][page]'
    );

    // Tool settings should not exist initially since no content types
    // are enabled.
    $this->assertSession()->elementNotExists('css', '#edit-tools-settings');

    // Enable a content type to make tools available.
    // Need to ensure the plugin is enabled and enable a content type.
    $this->submitForm([
      'plugin_settings[config][content_types][article]' => TRUE,
    ], 'Save configuration');

    // Reload the page to see the tools settings.
    $this->drupalGet('/admin/config/mcp/plugins/content/settings');
    $this->assertSession()->statusCodeEquals(200);

    // Now check that tool settings exist after enabling a content type.
    $this->assertSession()->elementExists('css', '#edit-tools-settings');
  }

}
