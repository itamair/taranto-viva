<?php

declare(strict_types=1);

namespace Drupal\Tests\mcp\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mcp\Plugin\McpPluginManager;

/**
 * Tests the MCP plugin manager with configurable plugins.
 *
 * @group mcp
 */
class McpPluginManagerKernelTest extends KernelTestBase {

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

    $this->installConfig(['mcp']);
    $this->pluginManager = $this->container->get('plugin.manager.mcp');
  }

  /**
   * Tests plugin discovery with configurable tools.
   */
  public function testPluginDiscoveryWithConfigurableTools(): void {
    // Get all plugin definitions.
    $definitions = $this->pluginManager->getDefinitions();

    $this->assertNotEmpty($definitions, 'Should discover MCP plugins');
    $this->assertArrayHasKey('general', $definitions, 'Should discover general plugin');

    // Check that plugins have expected properties.
    $generalDefinition = $definitions['general'];
    $this->assertArrayHasKey('id', $generalDefinition);
    $this->assertArrayHasKey('name', $generalDefinition);
    $this->assertArrayHasKey('description', $generalDefinition);
    $this->assertEquals('general', $generalDefinition['id']);

    // Create an instance and verify it has tools.
    $instance = $this->pluginManager->createInstance('general');
    $this->assertInstanceOf('\Drupal\mcp\Plugin\McpInterface', $instance);

    $tools = $instance->getTools();
    $this->assertNotEmpty($tools, 'General plugin should have tools');

    // Verify tools are objects with expected properties.
    foreach ($tools as $tool) {
      $this->assertObjectHasProperty('name', $tool);
      $this->assertObjectHasProperty('description', $tool);
      $this->assertObjectHasProperty('inputSchema', $tool);
    }
  }

  /**
   * Tests plugin instantiation with custom configuration.
   */
  public function testPluginInstantiationWithCustomConfiguration(): void {
    // Set custom configuration for the general plugin.
    $config = $this->config('mcp.settings');
    $customConfig = [
      'enabled' => TRUE,
      'roles' => ['test_role'],
      'config' => [
        'custom_setting' => 'custom_value',
      ],
      'tools' => [
        'info' => [
          'enabled' => FALSE,
          'roles' => ['admin'],
          'description' => 'Custom description for info',
        ],
      ],
    ];
    $config->set('plugins.general', $customConfig)->save();

    // Create instance with configuration from settings.
    $instance = $this->pluginManager->createInstance('general');

    // Verify configuration is applied.
    $instanceConfig = $instance->getConfiguration();
    $this->assertEquals($customConfig['enabled'], $instanceConfig['enabled']);
    $this->assertContains('test_role', $instanceConfig['roles']);
    $this->assertEquals($customConfig['config'], $instanceConfig['config']);

    // Verify tool configuration.
    $this->assertArrayHasKey('info', $instanceConfig['tools']);
    $this->assertEquals($customConfig['tools']['info'], $instanceConfig['tools']['info']);

    // Test tool customization (only description can be customized now).
    $customizedTools = $instance->getToolsWithCustomization();
    $foundCustomTool = FALSE;

    foreach ($customizedTools as $tool) {
      if ($tool->name === 'info') {
        // Name should remain unchanged.
        $this->assertEquals('info', $tool->name);
        // Description should be customized.
        $this->assertEquals('Custom description for info', $tool->description);
        $foundCustomTool = TRUE;
        break;
      }
    }

    $this->assertTrue($foundCustomTool, 'Should find customized tool');
  }

  /**
   * Tests plugin with empty tools configuration.
   */
  public function testPluginWithEmptyToolsConfiguration(): void {
    // Configure plugin with no tool overrides.
    $config = $this->config('mcp.settings');
    $config->set('plugins.general', [
      'enabled' => TRUE,
      'roles' => [],
      'config' => [],
    // Empty tools configuration.
      'tools' => [],
    ])->save();

    $instance = $this->pluginManager->createInstance('general');

    // Should still have tools with default settings.
    $tools = $instance->getTools();
    $this->assertNotEmpty($tools, 'Should have tools even with empty configuration');

    // All tools should be enabled by default.
    foreach ($tools as $tool) {
      $this->assertTrue(
        $instance->isToolEnabled($tool->name),
        "Tool {$tool->name} should be enabled by default"
      );
    }

    // Tools should not have customization when no configuration is provided.
    $customizedTools = $instance->getToolsWithCustomization();
    $originalTools = $instance->getTools();

    $this->assertCount(count($originalTools), $customizedTools, 'Should have same number of tools');

    // Map tools by name for comparison.
    $originalByName = [];
    foreach ($originalTools as $tool) {
      $originalByName[$tool->name] = $tool;
    }

    foreach ($customizedTools as $tool) {
      $this->assertArrayHasKey($tool->name, $originalByName, 'Tool name should exist in original tools');
      // When no customization, description should match original.
      $this->assertEquals($originalByName[$tool->name]->description, $tool->description, 'Tool description should match original when not customized');
    }
  }

}
