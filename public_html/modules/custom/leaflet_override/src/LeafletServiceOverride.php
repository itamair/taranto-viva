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
   * LeafletService constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   Current user service.
   * @param \Drupal\geofield\GeoPHP\GeoPHPInterface $geoPhpWrapper
   *   The geoPhpWrapper.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link
   *   The Link Generator service.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager
   *   The stream wrapper manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The stream wrapper manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend leaflet service.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   The file URL generator.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cachePermanent
   *   The permanent backend leaflet cache service.
   */
  public function __construct(
    protected AccountInterface $currentUser,
    protected GeoPHPInterface $geoPhpWrapper,
    protected ModuleHandlerInterface $moduleHandler,
    protected LinkGeneratorInterface $link,
    protected StreamWrapperManagerInterface $streamWrapperManager,
    protected RequestStack $requestStack,
    protected CacheBackendInterface $cache,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
    protected CacheBackendInterface $cachePermanent
  ) {
    parent::__construct(
      $currentUser,
      $geoPhpWrapper,
      $moduleHandler,
      $link,
      $streamWrapperManager,
      $requestStack,
      $cache,
      $fileUrlGenerator
    );
  }

  /**
   * Set Size If Empty or Invalid.
   *
   * @param array $feature
   *   The feature.
   * @param string $urlKey
   *   The url key.
   * @param string $sizeKey
   *   The size key.
   * @param string $cachePrefix
   *   The cache prefix.
   */
  protected function setSizeIfEmptyOrInvalid(array &$feature, string $urlKey, string $sizeKey, string $cachePrefix): void {
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
