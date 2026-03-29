<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Event;

use Drupal\search_api_typesense\Render\TypesenseSearchRenderContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched when building Typesense search render array.
 *
 * This event allows other modules to alter the render array before it's
 * returned by the TypesenseSearchRenderService. This is useful for:
 * - Overriding server configuration (host, port, protocol)
 * - Adding custom JavaScript settings
 * - Modifying theme variables
 * - Adding additional attached libraries or CSS.
 */
class SearchRenderEvent extends Event {

  /**
   * Event name constant.
   */
  public const NAME = 'search_api_typesense.search_render';

  /**
   * Constructs a SearchRenderEvent object.
   *
   * @param array $renderArray
   *   The render array to be altered.
   * @param \Drupal\search_api_typesense\Render\TypesenseSearchRenderContext $context
   *   The render context containing backend, indexes, and other configuration.
   */
  public function __construct(
    private array $renderArray,
    private readonly TypesenseSearchRenderContext $context,
  ) {
  }

  /**
   * Gets the render array.
   *
   * @return array
   *   The render array.
   */
  public function getRenderArray(): array {
    return $this->renderArray;
  }

  /**
   * Sets the render array.
   *
   * @param array $renderArray
   *   The render array.
   */
  public function setRenderArray(array $renderArray): void {
    $this->renderArray = $renderArray;
  }

  /**
   * Gets the render context.
   *
   * @return \Drupal\search_api_typesense\Render\TypesenseSearchRenderContext
   *   The render context.
   */
  public function getContext(): TypesenseSearchRenderContext {
    return $this->context;
  }

  /**
   * Helper method to get JavaScript settings from the render array.
   *
   * @return array
   *   The JavaScript settings array.
   */
  public function getJavaScriptSettings(): array {
    return $this->renderArray['content']['#attached']['drupalSettings']['search_api_typesense'] ?? [];
  }

  /**
   * Helper method to set JavaScript settings in the render array.
   *
   * @param array $settings
   *   The JavaScript settings to set.
   */
  public function setJavaScriptSettings(array $settings): void {
    $this->renderArray['content']['#attached']['drupalSettings']['search_api_typesense'] = $settings;
  }

  /**
   * Helper method to merge JavaScript settings into the render array.
   *
   * @param array $settings
   *   The JavaScript settings to merge.
   */
  public function mergeJavaScriptSettings(array $settings): void {
    $current_settings = $this->getJavaScriptSettings();
    $this->setJavaScriptSettings(\array_merge($current_settings, $settings));
  }

}
