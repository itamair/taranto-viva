<?php

namespace Drupal\mcp\Config;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\key\KeyRepositoryInterface;

/**
 * Provides MCP settings.
 */
class McpSettings {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly KeyRepositoryInterface $keyRepository,
  ) {}

  /**
   * Returns whether auth is enabled.
   */
  public function authIsEnabled(): bool {
    return $this->configFactory->get('mcp.settings')->get(
      'enable_auth'
    ) ?? FALSE;
  }

  /**
   * Returns the key ID for the shared secret.
   */
  public function getAuthSettings() {
    $auth_settings = $this->configFactory->get('mcp.settings')->get(
      'auth_settings'
    )
      ?? [];

    return [
      'enable_auth'       => $this->authIsEnabled(),
      'enable_token_auth' => $auth_settings['enable_token_auth'] ?? FALSE,
      'token_key_value'   => $this->keyRepository->getKey(
        $auth_settings['token_key'] ?? ''
      )?->getKeyValue(),
      'token_user'        => $auth_settings['token_user'] ?? '',
      'enable_basic_auth' => $auth_settings['enable_basic_auth'] ?? FALSE,
    ];
  }

}
