<?php

declare(strict_types=1);

namespace Drupal\mcp\Plugin;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Interface for MCP plugins.
 */
interface McpInterface extends
  PluginInspectionInterface,
  ConfigurableInterface,
  PluginFormInterface {

  /**
   * Check if the requirements are matched.
   *
   * @return bool
   *   TRUE if the requirements are matched, FALSE otherwise.
   */
  public function checkRequirements(): bool;

  /**
   * Get a description of plugin requirements.
   *
   * @return string
   *   A human-readable description of what is required for this plugin.
   *   Empty string if requirements are met or no specific requirements.
   */
  public function getRequirementsDescription(): string;

  /**
   * Get the available tools.
   *
   * @return array
   *   The available tools.
   */
  public function getTools(): array;

  /**
   * Get the available resources for this plugin.
   *
   * @return \Drupal\mcp\ServerFeatures\Resource[]
   *   An array of resources.
   */
  public function getResources(): array;

  /**
   * Get the available resource templates for this plugin.
   *
   * @return \Drupal\mcp\ServerFeatures\ResourceTemplate[]
   *   An array of resource templates.
   */
  public function getResourceTemplates(): array;

  /**
   * Execute a tool.
   *
   * @param string $toolId
   *   The tool to execute.
   * @param mixed $arguments
   *   The arguments to pass to the tool.
   *
   * @return array
   *   The result of the tool execution.
   */
  public function executeTool(string $toolId, mixed $arguments): array;

  /**
   * Read a resource.
   *
   * @param string $resourceId
   *   The resource identifier which is after the Scheme part of the URI.
   *
   * @return \Drupal\mcp\ServerFeatures\ResourceInterface[]
   *   The resource.
   */
  public function readResource(string $resourceId): array;

  /**
   * Is the plugin enabled or not.
   *
   * Plugin is enabled if:
   * - The plugin is enabled in the configuration or the configuration is not
   * set.
   */
  public function isEnabled();

  /**
   * Checks if the current user has access to this plugin.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   TRUE if the user has access, FALSE otherwise.
   */
  public function hasAccess(): AccessResult;

  /**
   * Get allowed roles for this plugin.
   *
   * @return array
   *   Array of role IDs that can access this plugin.
   */
  public function getAllowedRoles(): array;

  /**
   * Check if a specific tool is enabled.
   *
   * @param string $toolName
   *   The tool name to check.
   *
   * @return bool
   *   TRUE if the tool is enabled, FALSE otherwise.
   */
  public function isToolEnabled(string $toolName): bool;

  /**
   * Get allowed roles for a specific tool.
   *
   * @param string $toolName
   *   The tool name.
   *
   * @return array
   *   Array of role IDs that can access this tool.
   *   Returns empty array if tool uses plugin-level roles.
   */
  public function getToolAllowedRoles(string $toolName): array;

  /**
   * Check if the current user has access to a specific tool.
   *
   * @param string $toolName
   *   The tool name to check.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function hasToolAccess(string $toolName): AccessResult;

}
