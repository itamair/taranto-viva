<?php

declare(strict_types=1);

namespace Drupal\custom_module;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

/**
 * Provides utility methods for the custom_module.
 */
readonly class UtilityService {

  /**
   * Constructs a UtilityService object.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger channel factory.
   */
  public function __construct(
    protected ClientInterface $httpClient,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Returns the PMTiles URL for the latest Overture Maps places release.
   *
   * Fetches the Overture Maps releases page, extracts the latest release
   * identifier via regex, and constructs the corresponding S3 PMTiles URL.
   *
   * @return string|null
   *   The constructed PMTiles URL, or NULL if the release cannot be determined.
   */
  public function getLatestOverturePlacesPmtilesUrl(): ?string {
    $releases_page_url = 'https://docs.overturemaps.org/blog/tags/releases/';
    try {
      $response = $this->httpClient->get($releases_page_url);
      $html = (string) $response->getBody();
    }
    catch (GuzzleException $e) {
      $this->loggerFactory->get('custom_module')->error(
        'Failed to fetch Overture Maps releases page: @message',
        ['@message' => $e->getMessage()]
      );
      return NULL;
    }

    if (!preg_match('/(\d{4}-\d{2}-\d{2}\.\d)/', $html, $matches)) {
      $this->loggerFactory->get('custom_module')->warning(
        'Could not extract release version from the Overture Maps releases page.'
      );
      return NULL;
    }

    $release = $matches[1];
    return sprintf(
      'https://overturemaps-extras-us-west-2.s3.us-west-2.amazonaws.com/tiles/%s/places.pmtiles',
      $release
    );
  }

}
