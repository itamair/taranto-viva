<?php

namespace Drupal\mcp\PageCache;

use Drupal\Core\PageCache\RequestPolicyInterface;
use Drupal\mcp\Config\McpSettings;
use Symfony\Component\HttpFoundation\Request;

/**
 * Cache policy for pages served from mcp auth.
 */
class DisallowMcpAuthRequests implements RequestPolicyInterface {

  public function __construct(
    private readonly McpSettings $mcpSettings,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function check(Request $request) {
    return $this->isMcpRequest($request) ? static::DENY : NULL;
  }

  /**
   * Check if the request is an MCP request.
   */
  public function isMcpRequest(Request $request) {
    $auth_header = trim($request->headers->get('Authorization') ?? '');
    $auth_is_enabled = $this->mcpSettings->authIsEnabled();

    return str_starts_with($request->getRequestUri(), '/mcp')
      && str_starts_with($auth_header, 'Basic')
      && $auth_is_enabled;
  }

}
