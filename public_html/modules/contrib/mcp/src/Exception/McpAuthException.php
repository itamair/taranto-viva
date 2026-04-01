<?php

namespace Drupal\mcp\Exception;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Custom exception for MCP authentication errors.
 */
class McpAuthException extends AccessDeniedHttpException {

}
