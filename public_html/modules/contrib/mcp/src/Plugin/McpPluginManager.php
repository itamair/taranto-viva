<?php

declare(strict_types=1);

namespace Drupal\mcp\Plugin;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\mcp\Attribute\Mcp;

/**
 * Mcp plugin manager.
 */
class McpPluginManager extends DefaultPluginManager {

  /**
   * Constructs the object.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    protected ConfigFactoryInterface $config_factory,
  ) {
    parent::__construct(
      'Plugin/Mcp', $namespaces, $module_handler, McpInterface::class,
      Mcp::class
    );
    $this->alterInfo('mcp_info');
    $this->setCacheBackend($cache_backend, 'mcp_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance(
    $plugin_id,
    array $configuration = [],
  ): McpInterface {
    $config = $this->config_factory->get('mcp.settings');
    $plugin_config = $config->get("plugins.$plugin_id") ?? [];

    $defaults = [
      'enabled' => TRUE,
      'roles' => ['authenticated'],
      'config' => [],
      'tools' => [],
    ];

    $plugin_config = NestedArray::mergeDeep($defaults, $plugin_config);

    /** @var \Drupal\mcp\Plugin\McpInterface $instance */
    $instance = parent::createInstance(
      $plugin_id,
      NestedArray::mergeDeep(
        $plugin_config,
        $configuration
      )
    );

    return $instance;
  }

  /**
   * Get available plugins.
   *
   * @param bool $getDisabled
   *   Whether to include disabled plugins.
   * @param bool $getRestricted
   *   Whether to include restricted plugins.
   *
   * @return \Drupal\mcp\Plugin\McpInterface[]
   *   The available plugins.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the plugin cannot be instantiated.
   */
  public function getAvailablePlugins(
    bool $getDisabled = FALSE,
    bool $getRestricted = FALSE,
  ): array {
    $definitions = $this->getDefinitions();
    $available_plugins = [];
    foreach ($definitions as $plugin_id => $definition) {
      if (!preg_match('/^[a-zA-Z0-9-]+$/', $plugin_id)) {
        throw new \InvalidArgumentException(
          'Plugin ID must be made of letters, numbers, and hyphens. Invalid plugin: '
          . $plugin_id
        );
      }

      /** @var \Drupal\mcp\Plugin\McpInterface $instance */
      $instance = $this->createInstance($plugin_id);

      if (!$instance->checkRequirements()) {
        continue;
      }

      $matchEnabled = $getDisabled || $instance->isEnabled();
      $matchAllowed = $getRestricted || $instance->hasAccess()->isAllowed();

      if ($matchEnabled && $matchAllowed) {
        $available_plugins[$plugin_id] = $instance;
      }
    }

    return $available_plugins;
  }

}
