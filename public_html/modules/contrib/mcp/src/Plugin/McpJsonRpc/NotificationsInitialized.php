<?php

declare(strict_types=1);

namespace Drupal\mcp\Plugin\McpJsonRpc;

use Drupal\jsonrpc\Object\ParameterBag;
use Drupal\jsonrpc\Plugin\JsonRpcMethodBase;

/**
 * Handles the initialized notification from a client.
 *
 * @JsonRpcMethod(
 *   id = "notifications/initialized",
 *   usage = @Translation("Notification that the client has completed initialization."),
 * )
 */
class NotificationsInitialized extends JsonRpcMethodBase {

  /**
   * {@inheritdoc}
   */
  public function execute(ParameterBag $params) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function outputSchema() {
    return NULL;
  }

}
