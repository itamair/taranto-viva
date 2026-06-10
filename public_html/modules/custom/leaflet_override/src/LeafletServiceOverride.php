<?php

namespace Drupal\leaflet_override;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\geofield\GeoPHP\GeoPHPInterface;
use Drupal\leaflet\LeafletService;
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
   * Static cache for icon sizes.
   *
   * @var array
   */
  protected $iconSizes = [];

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

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
    CacheBackendInterface $cache_permanent
  ) {
    parent::__construct(
      $current_user,
      $geophp_wrapper,
      $module_handler,
      $link_generator,
      $stream_wrapper_manager,
      $request_stack,
      $cache,
      $file_url_generator
    );
    $this->cachePermanent = $cache_permanent;
  }

  /**
   * Set Size If Empty or Invalid.
   *
   * @param array $feature
   *   The feature.
   * @param string $type
   *   The type.
   * @param string $urlKey
   *   The url key.
   * @param string $sizeKey
   *   The size key.
   * @param string $cachePrefix
   *   The cache prefix.
   */
  protected function setSizeIfEmptyOrInvalid(array &$feature, string $type, string $urlKey, string $sizeKey, string $cachePrefix) {
    $url = $feature["icon"][$urlKey] ?? NULL;
    if (!empty($url) && isset($feature["icon"][$sizeKey])
      && (intval($feature["icon"][$sizeKey]["x"]) === 0 || intval($feature["icon"][$sizeKey]["y"]) === 0)) {

      $url = $this->generateAbsoluteString($url);
      $cache_index = $url . '-' . $feature["icon"][$sizeKey]["x"] . '-' . $feature["icon"][$sizeKey]["y"];

      // Use the cached size if present for this URL.
      $page_cache = &drupal_static($cachePrefix . ":" . $cache_index);
      if (is_array($page_cache) && array_key_exists('x', $page_cache) && array_key_exists('y', $page_cache)) {
        $feature["icon"][$sizeKey]["x"] = $page_cache['x'];
        $feature["icon"][$sizeKey]["y"] = $page_cache['y'];
      }
      elseif ($cached = $this->cachePermanent->get('leaflet_map_icon_size:' . $cache_index)) {
        $feature["icon"][$sizeKey]["x"] = $cached->data['x'];
        $feature["icon"][$sizeKey]["y"] = $cached->data['y'];
        // Set the size in the page cache.
        $page_cache = $feature["icon"][$sizeKey];
      }
      elseif ($this->fileExists($url)) {
        $fileParts = pathinfo($url);
        switch ($fileParts['extension']) {
          case "svg":
            $xml = simplexml_load_file($url);
            $attr = $xml ? $xml->attributes() : NULL;
            $size_x = !is_null($attr) && !empty($attr->width) ? intval($attr->width->__toString()) : 40;
            $size_y = !is_null($attr) && !empty($attr->height) ? intval($attr->height->__toString()) : 40;
            if (empty($feature["icon"][$sizeKey]["x"]) && !empty($feature["icon"][$sizeKey]["y"])) {
              $feature["icon"][$sizeKey]["x"] = intval($feature["icon"][$sizeKey]["y"] * $size_x / $size_y);
            }
            elseif (!empty($feature["icon"][$sizeKey]["x"]) && empty($feature["icon"][$sizeKey]["y"])) {
              $feature["icon"][$sizeKey]["y"] = intval($feature["icon"][$sizeKey]["x"] * $size_y / $size_x);
            }
            else {
              $feature["icon"][$sizeKey]["x"] = $size_x;
              $feature["icon"][$sizeKey]["y"] = $size_y;
            }
            break;

          default:
            if ($size = getimagesize($url)) {
              if (empty($feature["icon"][$sizeKey]["x"]) && !empty($feature["icon"][$sizeKey]["y"])) {
                $feature["icon"][$sizeKey]["x"] = intval($feature["icon"][$sizeKey]["y"] * $size[0] / $size[1]);
              }
              elseif (!empty($feature["icon"][$sizeKey]["x"]) && empty($feature["icon"][$sizeKey]["y"])) {
                $feature["icon"][$sizeKey]["y"] = intval($feature["icon"][$sizeKey]["x"] * $size[1] / $size[0]);
              }
              else {
                $feature["icon"][$sizeKey]["x"] = $size[0];
                $feature["icon"][$sizeKey]["y"] = $size[1];
              }
            }
        }
        // Set the size in the page cache.
        $page_cache = $feature["icon"][$sizeKey];

        // Set the feature icon size in the backend cache.
        $this->cachePermanent->set('leaflet_map_icon_size:' . $cache_index, $feature["icon"][$sizeKey]);
      }
    }
  }

}
