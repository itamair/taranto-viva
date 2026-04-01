<?php

declare(strict_types=1);

namespace Drupal\leaflet_choropleth\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a Classification attribute object.
 *
 * @see \Drupal\leaflet_choropleth\ClassificationPluginManager
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Classification extends Plugin {

  /**
   * Constructs a Classification attribute.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
  ) {}

}
