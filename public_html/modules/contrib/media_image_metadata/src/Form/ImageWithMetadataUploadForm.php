<?php

namespace Drupal\media_image_metadata\Form;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\media\MediaInterface;
use Drupal\media\MediaTypeInterface;
use Drupal\media_library\Form\FileUploadForm;

/**
 * Form to upload image with metadata into Media Library.
 *
 * @phpstan-ignore-next-line
 */
class ImageWithMetadataUploadForm extends FileUploadForm {

  /**
   * {@inheritdoc}
   */
  protected function createMediaFromValue(MediaTypeInterface $media_type, EntityStorageInterface $media_storage, $source_field_name, $file) {
    $media = parent::createMediaFromValue($media_type, $media_storage, $source_field_name, $file);
    assert($media instanceof MediaInterface);
    // ::prepareSave() is where metadata is fetched and saved into the mapped
    // fields. However, it's not part of MediaInterface since it needs to
    // be executed outside of the transaction for performance reasons.
    if (method_exists($media, 'prepareSave')) {
      $media->prepareSave();
    }
    return $media;
  }

}
