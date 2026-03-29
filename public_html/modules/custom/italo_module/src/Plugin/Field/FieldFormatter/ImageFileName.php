<?php

namespace Drupal\italo_module\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;

/**
 * Plugin implementation of the 'image_url' formatter.
 *
 * @FieldFormatter(
 *   id = "image_file_name",
 *   label = @Translation("Image File Name"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class ImageFileName extends ImageFormatter {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $items */
    if (empty($images = $this->getEntitiesToView($items, $langcode))) {
      // Early opt-out if the field is empty.
      return $elements;
    }

    /** @var \Drupal\image\ImageStyleInterface $image_style */
    $image_style = $this->imageStyleStorage->load($this->getSetting('image_style'));
    /** @var \Drupal\file\FileInterface[] $images */
    foreach ($images as $delta => $image) {
      $file_name = $image->getFilename();
      // Add cacheability metadata from the image and image style.
      $cacheability = CacheableMetadata::createFromObject($image);
      if ($image_style) {
        $cacheability->addCacheableDependency(CacheableMetadata::createFromObject($image_style));
      }

      $elements[$delta] = ['#markup' => $file_name];
      $cacheability->applyTo($elements[$delta]);
    }
    return $elements;
  }

}
