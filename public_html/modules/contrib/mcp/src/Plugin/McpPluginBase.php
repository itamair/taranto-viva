<?php

declare(strict_types=1);

namespace Drupal\mcp\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for MCP plugins.
 */
abstract class McpPluginBase extends PluginBase implements McpInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );

    $instance->currentUser = $container->get('current_user');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return NestedArray::mergeDeep(
      $this->defaultConfiguration(),
      $this->configuration,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = NestedArray::mergeDeep(
      $this->defaultConfiguration(),
      $configuration
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'enabled' => TRUE,
      'roles'   => ['authenticated'],
      'config'  => [],
      'tools'   => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ): array {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {}

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    if (!$form_state->getErrors()) {
      $this->configuration['enabled'] = (bool) $form_state->getValue('enabled');
      if ($config = $form_state->getValue('config')) {
        $this->configuration['config'] = $config;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function checkRequirements(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequirementsDescription(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getTools(): array {
    return [];
  }

  /**
   * Get tools with custom descriptions applied.
   *
   * This method wraps getTools() and applies any configured customizations
   * for tool descriptions.
   *
   * @return array
   *   Array of Tool objects with customizations applied.
   */
  public function getToolsWithCustomization(): array {
    $tools = $this->getTools();
    $config = $this->getConfiguration();
    $toolsConfig = $config['tools'] ?? [];

    $customizedTools = [];
    foreach ($tools as $tool) {
      // Clone the tool to avoid modifying the original.
      $customTool = clone $tool;

      if (isset($toolsConfig[$tool->name])) {
        $toolConfig = $toolsConfig[$tool->name];

        // Apply custom description if configured.
        if (!empty($toolConfig['description'])) {
          $customTool->description = $toolConfig['description'];
        }
      }

      $customizedTools[] = $customTool;
    }

    return $customizedTools;
  }

  /**
   * {@inheritdoc}
   */
  public function getResources(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceTemplates(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function executeTool(string $toolId, mixed $arguments): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function readResource(string $resourceId): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  final public function isEnabled(): bool {
    return $this->getConfiguration()['enabled'] ?? TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function hasAccess(): AccessResult {
    // Check if user has administrative permission (overrides all other checks).
    $adminAccess = AccessResult::allowedIfHasPermission(
      $this->currentUser, 'administer mcp configuration'
    );

    if ($adminAccess->isAllowed()) {
      return $adminAccess;
    }

    // Check if user has permission to use MCP server.
    $serverAccess = AccessResult::allowedIfHasPermission(
      $this->currentUser, 'use mcp server'
    );

    if (!$serverAccess->isAllowed()) {
      return $serverAccess;
    }

    $allowedRoles = $this->getAllowedRoles();
    if (empty($allowedRoles)) {
      return AccessResult::allowed();
    }

    $userRoles = $this->currentUser->getRoles();
    $hasRole = !empty(array_intersect($userRoles, $allowedRoles));

    return AccessResult::allowedIf($hasRole)
      ->addCacheContexts(['user.roles']);
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedRoles(): array {
    $config = $this->getConfiguration();
    return $config['roles'] ?? ['authenticated'];
  }

  /**
   * {@inheritdoc}
   */
  public function isToolEnabled(string $toolName): bool {
    $config = $this->getConfiguration();
    $toolConfig = $config['tools'][$toolName] ?? [];
    return $toolConfig['enabled'] ?? TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getToolAllowedRoles(string $toolName): array {
    $config = $this->getConfiguration();
    $toolConfig = $config['tools'][$toolName] ?? [];

    return $toolConfig['roles'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function hasToolAccess(string $toolName): AccessResult {
    $pluginAccess = $this->hasAccess();
    if (!$pluginAccess->isAllowed()) {
      return $pluginAccess;
    }

    if (!$this->isToolEnabled($toolName)) {
      return AccessResult::forbidden('Tool is disabled.');
    }

    $toolRoles = $this->getToolAllowedRoles($toolName);
    if (empty($toolRoles)) {
      return AccessResult::allowed()
        ->addCacheContexts(['user.roles']);
    }

    if ($this->currentUser->hasPermission('administer mcp configuration')) {
      return AccessResult::allowed()
        ->addCacheContexts(['user.permissions']);
    }

    $userRoles = $this->currentUser->getRoles();
    $hasRole = !empty(array_intersect($userRoles, $toolRoles));

    return AccessResult::allowedIf($hasRole)
      ->addCacheContexts(['user.roles']);
  }

  /**
   * Sanitize a tool name to be MCP-compliant.
   *
   * Converts to lowercase and replaces non-alphanumeric characters,
   * similar to Drupal's machine name conventions.
   *
   * @param string $toolName
   *   The tool name to sanitize.
   *
   * @return string
   *   The sanitized tool name.
   */
  public function sanitizeToolName(string $toolName): string {
    // Convert to lowercase.
    $name = strtolower($toolName);

    // Replace non-alphanumeric characters with underscores.
    $name = preg_replace('/[^a-z0-9_]+/', '_', $name);

    // Remove leading/trailing underscores.
    $name = trim($name, '_');

    // Ensure it starts with a letter or underscore (not a number).
    if (preg_match('/^[0-9]/', $name)) {
      $name = '_' . $name;
    }

    return $name;
  }

  /**
   * Generate a tool ID with plugin prefix.
   *
   * Ensures the final ID doesn't exceed 64 characters for compatibility
   * with MCP clients like Claude desktop.
   *
   * @param string $pluginId
   *   The plugin ID.
   * @param string $toolName
   *   The tool name.
   *
   * @return string
   *   The generated tool ID.
   */
  public function generateToolId(string $pluginId, string $toolName): string {
    $sanitizedName = $this->sanitizeToolName($toolName);
    $fullId = $pluginId . '_' . $sanitizedName;

    // Check if the ID exceeds 64 characters.
    if (strlen($fullId) > 64) {
      // Calculate maximum allowed length for the tool name part.
      // Reserve 7 characters for underscore and 6-char hash suffix.
      $maxToolNameLength = 64 - strlen($pluginId) - 1 - 7;

      if ($maxToolNameLength <= 0) {
        // Plugin ID itself is too long, truncate it.
        $truncatedPluginId = substr($pluginId, 0, 56);
        $hash = substr(md5($pluginId . '_' . $toolName), 0, 6);
        return $truncatedPluginId . '_' . $hash;
      }

      // Truncate the tool name and add a hash suffix.
      $truncatedName = substr($sanitizedName, 0, $maxToolNameLength);
      $hash = substr(md5($toolName), 0, 6);
      $fullId = $pluginId . '_' . $truncatedName . '_' . $hash;
    }

    return $fullId;
  }

}
