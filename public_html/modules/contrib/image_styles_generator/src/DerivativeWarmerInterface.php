<?php

namespace Drupal\image_styles_generator;

use Drupal\Core\Entity\EntityInterface;
use Drupal\image\ImageStyleInterface;

/**
 * Provide functionality to regenerate image styles derivatives.
 */
interface DerivativeWarmerInterface {

  /**
   * Regenerate derivative from file.
   *
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   The image style to regenerate.
   * @param \Drupal\Core\Entity\EntityInterface $file
   *   The file entity.
   *
   * @return string
   *   The derivative uri.
   */
  public function regenerateImageStyleDerivativeFromFile(ImageStyleInterface $image_style, EntityInterface $file): string;

}
