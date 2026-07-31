<?php

namespace Drupal\leaflet_override;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\geofield\GeoPHP\GeoPHPInterface;
use Drupal\leaflet\LeafletService;
use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a LeafletService Override class.
 */
class LeafletServiceOverride extends LeafletService {

  use StringTranslationTrait;

  /**
   * Current user service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The geoPhpWrapper service.
   *
   * @var \Drupal\geofield\GeoPHP\GeoPHPInterface
   */
  protected $geoPhpWrapper;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The Link generator Service.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected $link;

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The cache backend default service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * The http client, NULL until the container has been rebuilt.
   *
   * @var \GuzzleHttp\ClientInterface|null
   */
  protected $httpClient;

  /**
   * Icon sizes already resolved in this request, keyed by cache prefix and url.
   *
   * A FALSE value means the size could not be determined.
   *
   * @var array
   */
  protected $iconSizes = [];

  /**
   * Seconds to wait for the connection when an icon has to be requested.
   */
  const int ICON_CONNECT_TIMEOUT = 1;

  /**
   * Seconds to wait for the whole request when an icon has to be requested.
   */
  const int ICON_TIMEOUT = 2;

  /**
   * Seconds to remember that an icon size could not be determined.
   */
  const int ICON_FAILURE_TTL = 3600;

  /**
   * The permanent cache backend service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $cachePermanent;

  /**
   * LeafletService constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user service.
   * @param \Drupal\geofield\GeoPHP\GeoPHPInterface $geophp_wrapper
   *   The geoPhpWrapper.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The Link Generator service.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The stream wrapper manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend leaflet service.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file URL generator.
   * @param \GuzzleHttp\ClientInterface|null $http_client
   *   The http client. Optional, so that a container compiled before this
   *   argument was added does not fatal before it is rebuilt. Until then, the
   *   size of an icon hosted elsewhere cannot be determined.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_permanent
   *   The permanent backend leaflet cache service.
   */
  public function __construct(
    AccountInterface $current_user,
    GeoPHPInterface $geophp_wrapper,
    ModuleHandlerInterface $module_handler,
    LinkGeneratorInterface $link_generator,
    StreamWrapperManagerInterface $stream_wrapper_manager,
    RequestStack $request_stack,
    CacheBackendInterface $cache,
    FileUrlGeneratorInterface $file_url_generator,
    ClientInterface $http_client,
    CacheBackendInterface $cache_permanent,
  ) {
    parent::__construct(
      $current_user,
      $geophp_wrapper,
      $module_handler,
      $link_generator,
      $stream_wrapper_manager,
      $request_stack,
      $cache,
      $file_url_generator,
      $http_client,
    );
    $this->cachePermanent = $cache_permanent;
  }

  /**
   * Get the intrinsic size of an icon, from the cache when possible.
   *
   * Both outcomes are cached, so that an icon that cannot be measured is not
   * looked up again for every feature of every map.
   *
   * @param string $uri
   *   The icon uri, as configured.
   * @param string $url
   *   The absolute icon url.
   * @param string $cachePrefix
   *   The cache prefix.
   *
   * @return array|null
   *   The [width, height] of the icon, or NULL if it could not be determined.
   */
  protected function getIconSize(string $uri, string $url, string $cachePrefix): ?array {
    $key = $cachePrefix . ':' . $url;
    if (isset($this->iconSizes[$key])) {
      return $this->iconSizes[$key] ?: NULL;
    }

    $cid = 'leaflet_map_icon_size:' . $key;
    if ($cached = $this->cachePermanent->get($cid)) {
      $this->iconSizes[$key] = $cached->data ?: FALSE;
      return $cached->data ?: NULL;
    }

    $size = $this->readIconSize($uri, $url);
    $this->iconSizes[$key] = $size ?: FALSE;
    $expire = $size ? Cache::PERMANENT : time() + self::ICON_FAILURE_TTL;
    $this->cachePermanent->set($cid, $size, $expire);

    return $size;
  }

}
