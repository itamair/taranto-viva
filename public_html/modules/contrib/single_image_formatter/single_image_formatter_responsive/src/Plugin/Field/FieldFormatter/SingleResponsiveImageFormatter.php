<?php

namespace Drupal\single_image_formatter_responsive\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\responsive_image\Plugin\Field\FieldFormatter\ResponsiveImageFormatter;

/**
 * Plugin implementation of the 'single_responsive_image_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "single_responsive_image_formatter",
 *   label = @Translation("Single responsive image"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class SingleResponsiveImageFormatter extends ResponsiveImageFormatter {

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesToView(EntityReferenceFieldItemListInterface $items, $langcode) {
    $files = parent::getEntitiesToView($items, $langcode);
    $file = reset($files);
    return $file ? [$file] : [];
  }

}
