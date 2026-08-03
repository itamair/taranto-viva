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

  private const  RELEASES_PAGE =
    'https://docs.overturemaps.org/blog/tags/releases/';

  private const  PMTILES_URL_TEMPLATE =
    'https://overturemaps-extras-us-west-2.s3.us-west-2.amazonaws.com/tiles/%s/%s.pmtiles';

  private const  RELEASE_REGEX =
    '/(\d{4}-\d{2}-\d{2}\.\d+)/';

  private const  CACHE_ID = 'custom_module:overture_latest_release';

  private const  CACHE_TTL = 86400;

  private const  HTTP_TIMEOUT = 10;

  private const  RELEASES_RSS =
    'https://docs.overturemaps.org/blog/rss.xml';

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

    $release = $this->fetchLatestRelease();

    if ($release === NULL) {
      if ($cache = $this->cache->get(self::CACHE_ID, TRUE)) {
        return $cache->data;
      }
      return NULL;
    }

    $this->cache->set(
      self::CACHE_ID,
      $release,
      time() + self::CACHE_TTL
    );

    return $release;
  }

  /**
   * Fetches the latest release from RSS, falling back to the HTML page.
   */
  private function fetchLatestRelease(): ?string {

    foreach ([self::RELEASES_RSS, self::RELEASES_PAGE] as $url) {
      try {
        $response = $this->httpClient->get($url, [
          'timeout' => self::HTTP_TIMEOUT,
        ]);

        if ($response->getStatusCode() !== 200) {
          continue;
        }

        $body = (string) $response->getBody();

        if (preg_match(self::RELEASE_REGEX, $body, $matches)) {
          return $matches[1];
        }
      }
      catch (GuzzleException) {
      }
    }

    $this->logger->warning('Unable to determine latest Overture release from RSS or HTML.');

    return NULL;
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
