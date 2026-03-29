<?php

declare(strict_types=1);

namespace Drupal\mcp\ParamConverter;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\mcp\Plugin\McpInterface;
use Drupal\mcp\Plugin\McpPluginManager;
use Symfony\Component\Routing\Route;

/**
 * Parameter converter for upcasting mcp plugin IDs to full objects.
 *
 * In order to use it you should specify some additional options in your route:
 * @code
 *  example.route:
 *    path: foo/{example}
 *    options:
 *      parameters:
 *        example:
 *          type: mcp_plugin
 * @endcode
 */
class McpPluginConverter implements ParamConverterInterface {

  /**
   * Constructs a new McpPluginConverter.
   */
  public function __construct(
    protected McpPluginManager $pluginManagerMcp,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults): ?McpInterface {
    try {
      return $this->pluginManagerMcp->createInstance($value);
    }
    catch (PluginException $e) {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route): bool {
    return isset($definition['type']) && $definition['type'] === 'mcp_plugin';
  }

}
