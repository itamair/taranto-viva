<?php

namespace Drupal\mcp\Plugin;

use Drupal\jsonrpc\Plugin\JsonRpcMethodBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base implementation for MCP JSON RPC methods.
 */
abstract class McpJsonRpcMethodBase extends JsonRpcMethodBase {

  /**
   * The MCP plugin manager.
   *
   * @var \Drupal\mcp\Plugin\McpPluginManager
   */
  protected McpPluginManager $mcpPluginManager;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    $instance = parent::create(
      $container, $configuration, $plugin_id, $plugin_definition
    );

    $instance->mcpPluginManager = $container->get('plugin.manager.mcp');

    return $instance;
  }

}
