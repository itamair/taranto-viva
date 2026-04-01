<?php

declare(strict_types=1);

namespace Drupal\leaflet_choropleth\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a ColorScale attribute object.
 *
 * Plugin namespace: Plugin\ColorScale.
 *
 * @see \Drupal\leaflet_choropleth\ColorScalePluginManager
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ColorScale extends Plugin {

  /**
   * Constructs a ColorScale attribute.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
  ) {}

}
