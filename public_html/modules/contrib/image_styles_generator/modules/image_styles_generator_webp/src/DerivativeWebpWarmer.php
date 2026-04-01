<?php

namespace Drupal\image_styles_generator_webp;

use Drupal\Core\Entity\EntityInterface;
use Drupal\image\ImageStyleInterface;
use Drupal\image_styles_generator\DerivativeWarmer;
use Drupal\webp\Webp;
use Psr\Log\LoggerInterface;

/**
 * Provide functionality to regenerate image styles derivatives.
 */
class DerivativeWebpWarmer extends DerivativeWarmer {

  /**
   * The webp service.
   *
   * @var \Drupal\webp\Webp
   */
  protected $webp;

  /**
   * Constructs a Regenerator object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel to log all info.
   * @param \Drupal\webp\Webp $webp
   *   The webp service.
   */
  public function __construct(LoggerInterface $logger, Webp $webp) {
    parent::__construct($logger);
    $this->webp = $webp;
  }

  /**
   * {@inheritdoc}
   */
  public function regenerateImageStyleDerivativeFromFile(ImageStyleInterface $image_style, EntityInterface $file): string {
    $derivative_uri = parent::regenerateImageStyleDerivativeFromFile($image_style, $file);
    $this->webp->createWebpCopy($derivative_uri);
    return $derivative_uri;
  }

}
