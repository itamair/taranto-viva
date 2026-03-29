<?php

declare(strict_types=1);

namespace Drupal\mcp\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\jsonrpc\Plugin\JsonRpcMethodManager;

/**
 * Provides the McpJsonRpcMethod plugin manager.
 *
 * @phpstan-ignore-next-line
 */
class McpJsonRpcMethodManager extends JsonRpcMethodManager {

  /**
   * Constructs a new McpJsonRpcMethodManager object.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    $this->alterInfo(FALSE);
    parent::__construct($namespaces, $cache_backend, $module_handler);

    $this->setCacheBackend($cache_backend, 'mcp_jsonrpc_plugins');
    $this->subdir = 'Plugin/McpJsonRpc';
  }

}
