<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;
use GraphQL\Error\DebugFlag;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

/**
 * Tests your GraphQL functionality.
 *
 * @group graphql_compose
 */
abstract class GraphQLComposeBrowserTestBase extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'graphql_compose',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The GraphQL endpoint.
   *
   * @var string
   */
  protected string $graphqlEndpointUrl = '/graphql';

  /**
   * The GraphQL permissions required to view the schema.
   *
   * @var array
   */
  protected array $graphqlPermissions = [
    'execute graphql_compose_server arbitrary graphql requests',
  ];

  /**
   * {@inheritdoc}
   */
  public function installDrupal() {
    try {
      parent::installDrupal();
    }
    catch (\Throwable) {
      // Double check it's not just a slow to upgrade dependency.
      // @see https://www.drupal.org/project/graphql_compose/issues/3446989
      $this->container->get('module_installer')->install(['backward_compatibility'], TRUE);
      $this->installModulesFromClassProperty($this->container);

      // Continue on from the failure.
      $this->container->get('cache_tags.invalidator')->resetChecksums();
      Url::fromRoute('<front>')->setAbsolute()->toString();
      $this->container->get('stream_wrapper_manager')->register();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->grantPermissions(
      Role::load(Role::ANONYMOUS_ID),
      $this->graphqlPermissions
    );

    $config = $this->config('graphql.graphql_servers.graphql_compose_server');
    $config->set('debug_flag', DebugFlag::INCLUDE_DEBUG_MESSAGE);
    $config->set('caching', TRUE);
    $config->save();
  }

  /**
   * Executes a query.
   *
   * @param string $query
   *   The query to execute.
   * @param array $variables
   *   The query variables.
   *
   * @return array
   *   The query json result.
   */
  protected function executeQuery(string $query, array $variables = []): array {

    try {
      $response = $this->getResponse($query, $variables);
      $json = $this->getJson($response);
    }
    catch (RequestException | ClientException $exception) {
      dump($query);
      dump($exception->getMessage());
      dump($exception->getResponse()->getBody()->getContents());
      throw $exception;
    }
    catch (\Throwable $exception) {
      dump($query);
      dump($exception->getMessage());
      throw $exception;
    }

    $this->assertNotNull($json);
    $this->assertEquals(200, $response->getStatusCode());

    return $json;
  }

  /**
   * Get a response back from the GraphQL endpoint.
   *
   * @param string $query
   *   The query to execute.
   * @param array $variables
   *   The query variables.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response.
   */
  protected function getResponse(string $query, array $variables = []): ResponseInterface {

    $url = $this->buildUrl($this->graphqlEndpointUrl, ['absolute' => TRUE]);

    return $this->getHttpClient()->request('POST', $url, [
      'json' => [
        'query' => $query,
        'variables' => $variables,
      ],
      'cookies' => $this->getSessionCookies(),
    ]);
  }

  /**
   * Get the JSON from a HTTP response.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The response.
   *
   * @return array|null
   *   The JSON.
   */
  protected function getJson(ResponseInterface $response): ?array {
    return Json::decode($response->getBody());
  }

  /**
   * Set config for GraphQL Compose.
   *
   * @param string $key
   *   The entity type id.
   * @param mixed $options
   *   The options to set.
   */
  protected function setConfig(string $key, $options): void {
    $config = $this->config('graphql_compose.settings');
    $config->set($key, $options);
    $config->save();

    _graphql_compose_cache_flush();
  }

  /**
   * Set an entity config.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $entity_bundle_id
   *   The entity bundle id.
   * @param array $options
   *   The options to set.
   */
  protected function setEntityConfig(string $entity_type_id, string $entity_bundle_id, array $options = []): void {
    foreach ($options as $key => $value) {
      $this->setConfig('entity_config.' . $entity_type_id . '.' . $entity_bundle_id . '.' . $key, $value);
    }
  }

  /**
   * Set an entity config.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $entity_bundle_id
   *   The entity bundle id.
   * @param string $field_name
   *   The field name.
   * @param array $options
   *   The options to set.
   */
  protected function setFieldConfig(string $entity_type_id, string $entity_bundle_id, string $field_name, array $options = []): void {
    foreach ($options as $key => $value) {
      $this->setConfig('field_config.' . $entity_type_id . '.' . $entity_bundle_id . '.' . $field_name . '.' . $key, $value);
    }
  }

}
