<?php

namespace Drupal\mcp\Authentication\Provider;

use Drupal\mcp\Exception\McpAuthException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\mcp\Config\McpSettings;
use Drupal\mcp\PageCache\DisallowMcpAuthRequests;
use Drupal\user\UserAuthenticationInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\user\UserAuthInterface;

/**
 * MCP Authentication Provider.
 */
class McpAuthProvider implements AuthenticationProviderInterface {

  public function __construct(
    private readonly McpSettings $mcpSettings,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly UserAuthInterface|UserAuthenticationInterface $userAuth,
    private readonly FloodInterface $flood,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly DisallowMcpAuthRequests $disallowMcpAuthRequests,
  ) {
    if (!$userAuth instanceof UserAuthenticationInterface) {
      @trigger_error(
        'The $user_auth parameter implementing UserAuthInterface is deprecated in drupal:10.3.0 and will be removed in drupal:12.0.0. Implement UserAuthenticationInterface instead. See https://www.drupal.org/node/3411040'
      );
    }
  }

  /**
   * {@inheritDoc}
   */
  public function applies(Request $request) {
    return $this->disallowMcpAuthRequests->isMcpRequest($request);
  }

  /**
   * Authenticates the incoming request based on the configured auth methods.
   *
   * Flood protection: Is similar to the
   * Drupal\basic_auth\Authentication\Provider.
   *
   * Refactor it when:
   * https://www.drupal.org/project/drupal/issues/2431357 merged.
   */
  public function authenticate(Request $request) {
    $flood_config = $this->configFactory->get('user.flood');
    if (!$this->flood->isAllowed(
      'mcp_auth.failed_login_ip', $flood_config->get('ip_limit'),
      $flood_config->get('ip_window')
    )
    ) {
      $this->flood->register(
        'mcp_auth.failed_login_ip', $flood_config->get('ip_window')
      );
      throw new McpAuthException(
        "Too many failed login attempts (IP threshold reached)."
      );
    }

    $authHeader = $request->headers->get('Authorization');
    if (empty($authHeader) || stripos($authHeader, 'Basic ') !== 0) {
      throw new McpAuthException(
        "Missing or invalid Authorization header."
      );
    }

    $encoded = trim(substr($authHeader, 6));
    $decoded = base64_decode($encoded);
    if ($decoded === FALSE) {
      throw new McpAuthException(
        "Invalid base64 encoded credentials."
      );
    }

    if (str_contains($decoded, ':')) {
      $result = $this->authenticateBasic($request, $decoded, $flood_config);
    }
    else {
      $result = $this->authenticateToken($request, $decoded, $flood_config);
    }

    if ($result === NULL) {
      $this->flood->register(
        'mcp_auth.failed_login_ip', $flood_config->get('ip_window')
      );
      throw new McpAuthException(
        "Authentication failed: Invalid credentials."
      );
    }

    return $result;
  }

  /**
   * Authenticates the request using basic auth.
   */
  private function authenticateBasic(
    Request $request,
    string $decoded,
    $flood_config,
  ) {
    $auth_settings = $this->mcpSettings->getAuthSettings();
    if (empty($auth_settings['enable_basic_auth'])) {
      return NULL;
    }
    [$username, $password] = explode(':', $decoded, 2);
    $account = FALSE;
    if ($this->userAuth instanceof UserAuthenticationInterface) {
      $lookup = $this->userAuth->lookupAccount($username);
      if ($lookup) {
        $account = $lookup;
      }
    }
    else {
      $accounts = $this->entityTypeManager->getStorage('user')
        ->loadByProperties(['name' => $username]);
      $account = reset($accounts);

      // If not found and username contains @, try loading by email.
      // This is useful for sites where users log in with email addresses.
      if (!$account && str_contains($username, '@')) {
        $accounts = $this->entityTypeManager->getStorage('user')
          ->loadByProperties(['mail' => $username]);

        $account = reset($accounts);
      }
    }
    if ($account && !$account->isBlocked() && $account->isActive()) {
      $identifier = $flood_config->get('uid_only') ? $account->id()
        : $account->id() . '-' . $request->getClientIP();
      if ($this->flood->isAllowed(
        'mcp_auth.failed_login_user', $flood_config->get('user_limit'),
        $flood_config->get('user_window'), $identifier
      )
      ) {
        $authenticated = FALSE;
        if ($this->userAuth instanceof UserAuthenticationInterface) {
          $authenticated = $this->userAuth->authenticateAccount(
            $account, $password
          );
        }
        else {
          $authenticated = $this->userAuth->authenticate($username, $password);
        }

        if ($authenticated) {
          $this->flood->clear('mcp_auth.failed_login_user', $identifier);

          return $account;
        }
        else {
          $this->flood->register(
            'mcp_auth.failed_login_user', $flood_config->get('user_window'),
            $identifier
          );
        }
      }
    }

    return NULL;
  }

  /**
   * Authenticates the request using a token.
   */
  private function authenticateToken(
    Request $request,
    string $decoded,
    $flood_config,
  ) {
    $auth_settings = $this->mcpSettings->getAuthSettings();
    if (empty($auth_settings['enable_token_auth'])) {
      return NULL;
    }
    $expectedToken = $auth_settings['token_key_value'] ?? '';
    $token_user_id = $auth_settings['token_user'] ?? '';

    // Validate token user configuration.
    if (empty($token_user_id)) {
      throw new McpAuthException(
        "Token authentication is enabled but no user is configured."
      );
    }

    $identifier = $flood_config->get('uid_only')
      ? $token_user_id
      : $token_user_id . '-' . $request->getClientIP();
    if ($this->flood->isAllowed(
      'mcp_auth.failed_login_token', $flood_config->get('user_limit'),
      $flood_config->get('user_window'), $identifier
    )
    ) {
      if ($decoded === $expectedToken) {
        $this->flood->clear('mcp_auth.failed_login_token', $identifier);

        $user = $this->entityTypeManager
          ->getStorage('user')
          ->load($token_user_id);

        if (!$user || !$user->isActive()) {
          throw new McpAuthException(
            "Configured token user does not exist or is not active."
          );
        }

        return $user;
      }
      else {
        $this->flood->register(
          'mcp_auth.failed_login_token', $flood_config->get('user_window'),
          $identifier
        );
      }
    }

    return NULL;
  }

}
