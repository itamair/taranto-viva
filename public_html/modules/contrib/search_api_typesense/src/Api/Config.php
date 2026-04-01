<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Api;

use Psr\Http\Client\ClientInterface;

/**
 * The Search Api Typesense client factory.
 */
class Config {

  /**
   * Config constructor.
   *
   * @param string $api_key
   *   The Typesense API key.
   * @param array|null $nearest_node
   *   The nearest Typesense node.
   * @param array $nodes
   *   The Typesense nodes.
   * @param int $retry_interval_seconds
   *   The Typesense retry interval in seconds.
   * @param \Psr\Http\Client\ClientInterface $http_client
   *   Drupal HTTP client.
   */
  public function __construct(
    public readonly string $api_key,
    public readonly ?array $nearest_node,
    public readonly array $nodes,
    public readonly int $retry_interval_seconds,
    public readonly ClientInterface $http_client,
  ) {}

  /**
   * Returns the config as an array.
   *
   * @return array
   *   The config as an array.
   */
  public function toArray(): array {
    return [
      'api_key' => $this->api_key,
      'nearest_node' => $this->nearest_node,
      'nodes' => $this->nodes,
      'retry_interval_seconds' => $this->retry_interval_seconds,
      'client' => $this->http_client,
    ];
  }

  /**
   * Checks if the config is valid.
   *
   * @return bool
   *   TRUE if the config is valid, FALSE otherwise.
   */
  public function valid(): bool {
    return $this->api_key !== ''
      && \count($this->nodes) > 0;
  }

}
