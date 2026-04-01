<?php

declare(strict_types=1);

namespace Drupal\Tests\jsonrpc\Functional;

use Drupal\Core\Url;
use Drupal\Tests\jsonrpc_discovery\Functional\JsonRpcDiscoveryFunctionalTestBase;
use Drupal\user\UserInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Tests the jsonrpc/methods endpoint.
 *
 * @group jsonrpc
 */
class JsonRpcDiscoveryHttpTest extends JsonRpcDiscoveryFunctionalTestBase {
  const PLUGINS_METHOD_NAME = 'List defined plugins';

  /**
   * Executes a request to jsonrpc/methods.
   *
   * @return string
   *   The absolute url.
   */
  protected function getMethodsUrl(): string {
    return Url::fromRoute('jsonrpc.method_collection')
      ->setAbsolute()->toString();
  }

  /**
   * Provides a basic auth header.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user account.
   *
   * @return string
   *   The basic auth header value formatted for Guzzle.
   */
  protected function getAuthForUser(UserInterface $user): string {
    $name = $user->getAccountName();
    $pass = $user->passRaw;
    return 'Basic ' . base64_encode($name . ':' . $pass);
  }

  /**
   * Gets the JSON-RPC result from the response.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The response.
   *
   * @return string
   *   The JSON-RPC result from the response body.
   */
  protected function getJsonRpcResultFromResponse(ResponseInterface $response): string {
    // Need to use (string) to get JSON-RPC body.
    // See https://stackoverflow.com/a/30549372/1209486.
    return (string) $response->getBody();
  }

  /**
   * Tests getting the methods as an anonymous user.
   */
  public function testMethodsAnon(): void {
    // Anon does not have access to JSON-RPC services.
    $method_url = $this->getMethodsUrl();
    try {
      \Drupal::httpClient()->get($method_url, [
        'body' => NULL,
        'headers' => [],
      ]);
    }
    catch (\Exception $e) {
      $this->assertStringContainsString('401 Unauthorized', $e->getMessage());
    }
  }

  /**
   * Tests getting the methods as an auth user.
   */
  public function testMethodsAuth(): void {
    $this->drupalLogin($this->user);
    $has_plugins_method_permission = \Drupal::currentUser()->hasPermission('administer site configuration');
    $this->assertFalse($has_plugins_method_permission, 'User account has "administer site configuration" permission to access the Plugins JSON-RPC method, but it should not have this permission.');

    $method_url = $this->getMethodsUrl();
    $auth_response = \Drupal::httpClient()->get($method_url, [
      'body' => NULL,
      'headers' => [
        'Authorization' => $this->getAuthForUser($this->user),
      ],
    ]);
    $this->assertEquals(200, $auth_response->getStatusCode());
    // Auth does not have access to the plugins method.
    $this->assertStringNotContainsString(self::PLUGINS_METHOD_NAME, $this->getJsonRpcResultFromResponse($auth_response));
  }

  /**
   * Tests getting the methods as an admin user.
   */
  public function testMethodsAdmin(): void {
    $this->drupalLogin($this->adminUser);
    $has_plugins_method_permission = \Drupal::currentUser()->hasPermission('administer site configuration');
    $this->assertTrue($has_plugins_method_permission, 'Admin account does not have permission to access the Plugins JSON-RPC method.');

    $method_url = $this->getMethodsUrl();
    $admin_response = \Drupal::httpClient()->get($method_url, [
      'body' => NULL,
      'headers' => [
        'Authorization' => $this->getAuthForUser($this->adminUser),
      ],
    ]);
    $this->assertEquals(200, $admin_response->getStatusCode());
    // Admin does have access to the plugins method.
    $this->assertStringContainsString(self::PLUGINS_METHOD_NAME, $this->getJsonRpcResultFromResponse($admin_response));
  }

  /**
   * Tests getting the plugins method as an admin user.
   */
  public function testPluginsAdmin(): void {
    $this->drupalLogin($this->adminUser);
    $has_plugins_method_permission = \Drupal::currentUser()->hasPermission('administer site configuration');
    $this->assertTrue($has_plugins_method_permission, 'Admin account does not have permission to access the Plugins JSON-RPC method.');

    $method_url = $this->getMethodsUrl() . '/plugins.list';
    $admin_response = \Drupal::httpClient()->get($method_url, [
      'body' => NULL,
      'headers' => [
        'Authorization' => $this->getAuthForUser($this->adminUser),
      ],
    ]);
    $this->assertEquals(200, $admin_response->getStatusCode());
    $this->assertStringContainsString(self::PLUGINS_METHOD_NAME, $this->getJsonRpcResultFromResponse($admin_response));
  }

}
