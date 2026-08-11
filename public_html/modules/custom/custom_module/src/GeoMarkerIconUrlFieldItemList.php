<?php

namespace Drupal\custom_module;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\media\MediaInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;

/**
 * Generates a GeoMarkerIconUrlFieldItemList.
 */
class GeoMarkerIconUrlFieldItemList extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * Whether the value has been calculated.
   *
   * @var bool
   */
  protected bool $isCalculated = FALSE;

  /**
   * {@inheritdoc}
   *
   * Generate the Value for the Geo Marker Icon Url Path.
   */
  protected function computeValue(): void {
    if (!$this->isCalculated) {
      $entity = $this->getEntity();
      $bundle = $entity->bundle();
      $parent_entity = $entity->getParentEntity();
      if ($parent_entity) {
        $parent_bundle = $parent_entity->bundle();
      }
      $image_style = NULL;
      $value = '';
      if ($entity instanceof ParagraphInterface) {
        $paragraph_type = $entity->bundle();
        switch ($paragraph_type) {
          case "geoimage":
            $media = $entity->field_geoimage->entity;
            $image_style = 'image_map_square_marker';
            break;

          case "image":
            $media = isset($entity->field_image) ? $entity->field_image->entity : NULL;
            $image_style = 'image_map_marker';
            break;

          case "location":
            $media = isset($entity->field_marker_image) ? $entity->field_marker_image->entity : NULL;
            $image_style = (isset($parent_bundle) &&  $parent_bundle === 'event') ? 'image_map_marker_height_100' : 'image_map_marker';
            if (!$media instanceof MediaInterface && $entity->field_location_type->entity instanceof ContentEntityInterface) {
              $media = $entity->field_location_type->entity->field_place_type_icon->entity;
            }
            break;
        }

        if (isset($media) && $media instanceof MediaInterface && isset($media->field_media_image) && $media->field_media_image->entity instanceof FileInterface) {
          $value = $this->getImageValue($media->field_media_image->entity, $image_style);
        }
      }
      $this->list[0] = $this->createItem(0, $value);
      $this->isCalculated = TRUE;
    }
  }

  /**
   * Get Image Value from FileInterface and Image Style.
   *
   * @param \Drupal\file\FileInterface $file_entity
   *   The file entity.
   * @param string|null $image_style
   *   The Image Style string.
   *
   * @return string
   *   The Image value path string.
   */
  protected function getImageValue(FileInterface $file_entity, ?string $image_style): string {
    $image_uri = $file_entity->getFileUri();
    // If there is an image style requested, and it is not an SVG file.
    if (isset($image_style) && !str_contains($file_entity->getMimeType(), 'svg')) {
      $style = ImageStyle::load($image_style);
      $value = \Drupal::service('file_url_generator')
        // Generate Absolute Path to fix the pan of geoimages under their
        // location. Would align to the up-left corner of the image, otherwise.
        ->generateAbsoluteString($style->buildUri($image_uri));
    }
    else {
      $value = \Drupal::service('file_url_generator')
        // Generate Absolute Path to fix the pan of geoimages under their
        // location. Would align to the up-left corner of the image, otherwise.
        ->generateAbsoluteString($image_uri);
    }
    return $value;
  }

}
