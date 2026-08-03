<?php

declare(strict_types=1);

namespace Drupal\custom_module;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Provides utility methods for the custom module.
 */
final readonly class UtilityService {

  private const string RELEASES_PAGE =
    'https://docs.overturemaps.org/blog/tags/releases/';

  private const string PMTILES_URL_TEMPLATE =
    'https://overturemaps-extras-us-west-2.s3.us-west-2.amazonaws.com/tiles/%s/%s.pmtiles';

  private const string RELEASE_REGEX =
    '/(\d{4}-\d{2}-\d{2}\.\d+)/';

  private const string CACHE_ID = 'custom_module:overture_latest_release';

  private const int CACHE_TTL = 86400;

  private const int HTTP_TIMEOUT = 10;

  /**
   * Constructs a UtilityService object.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The default cache bin.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel factory.
   */
  public function __construct(
    protected ClientInterface $httpClient,
    protected CacheBackendInterface $cache,
    protected LoggerChannelInterface $logger,
  ) {}

  /**
   * Returns the latest available Overture release.
   */
  public function getLatestOvertureRelease(): ?string {

    if ($cache = $this->cache->get(self::CACHE_ID)) {
      return $cache->data;
    }

    try {
      $response = $this->httpClient->get(self::RELEASES_PAGE, [
        'timeout' => self::HTTP_TIMEOUT,
      ]);

      if ($response->getStatusCode() !== 200) {
        $this->logger->warning(
          'Unexpected HTTP status while fetching Overture releases: @status',
          ['@status' => $response->getStatusCode()]
        );
        return NULL;
      }

      $html = (string) $response->getBody();
    }
    catch (GuzzleException $e) {
      $this->logger->error(
        'Unable to retrieve Overture releases: @message',
        ['@message' => $e->getMessage()]
      );
      return NULL;
    }

    if (!preg_match(self::RELEASE_REGEX, $html, $matches)) {
      $this->logger->warning(
        'Unable to determine latest Overture release.'
      );
      return NULL;
    }

    $release = $matches[1];

    $this->cache->set(
      self::CACHE_ID,
      $release,
      time() + self::CACHE_TTL
    );

    return $release;
  }

  /**
   * Returns the PMTiles URL for the latest Overture release.
   */
  public function getLatestPmtilesUrl(string $theme = 'places'): ?string {

    $release = $this->getLatestOvertureRelease();

    if ($release === NULL) {
      return NULL;
    }

    return sprintf(
      self::PMTILES_URL_TEMPLATE,
      $release,
      $theme,
    );
  }

}
