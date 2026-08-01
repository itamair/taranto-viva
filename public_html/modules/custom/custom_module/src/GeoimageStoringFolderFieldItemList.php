<?php

namespace Drupal\custom_module;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\node\NodeInterface;

/**
 * Generates a GeoimageStoringFolderFieldItemList.
 */
class GeoimageStoringFolderFieldItemList extends FieldItemList {

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
   * Generate the Value for the Geoimage Storing Folder, only for Nodes.
   */
  protected function computeValue() {
    if (!$this->isCalculated) {
      $entity = $this->getEntity();
      if ($entity instanceof NodeInterface) {
        switch ($entity->bundle()) {
          case "geoimage":
            $value = 'photo_albums/Taranto_Images';
            break;

          case "territorial_report":
          case "event":
            $value = 'media_image';
            break;

          default:
            $value = '';
        }
        $this->list[0] = $this->createItem(0, $value);
        $this->isCalculated = TRUE;
      }
    }
  }

}
