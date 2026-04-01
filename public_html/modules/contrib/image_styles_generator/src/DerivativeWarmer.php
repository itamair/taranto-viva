<?php

namespace Drupal\image_styles_generator;

use Drupal\Core\Entity\EntityInterface;
use Drupal\image\ImageStyleInterface;
use Psr\Log\LoggerInterface;

/**
 * Derivative warmer base class.
 */
class DerivativeWarmer implements DerivativeWarmerInterface {

  /**
   * The logger channel to log all info.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Constructs a Regenerator object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel to log all info.
   */
  public function __construct(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function regenerateImageStyleDerivativeFromFile(ImageStyleInterface $image_style, EntityInterface $file): string {
    $derivative_uri = $image_style->buildUri($file->getFileUri());
    $image_style->createDerivative($file->getFileUri(), $derivative_uri);
    return $derivative_uri;
  }

}
