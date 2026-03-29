<?php

namespace Drupal\entity_mesh\Plugin\views\field;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Link;
use Drupal\views\Plugin\views\field\FieldPluginBase;

/**
 * Provides a basic class to generate links from entities.
 */
abstract class BaseLinkSource extends FieldPluginBase {

  /**
   * Generates a link to the entity.
   *
   * @param string $link_label
   *   The link label.
   * @param string $entity_type
   *   The entity type.
   * @param string $entity_id
   *   The entity id.
   * @param string $langcode
   *   The langcode.
   *
   * @return mixed
   *   The link or entity id.
   */
  protected function generateLink(string $link_label, string $entity_type, string $entity_id, string $langcode = '') {
    if (empty($entity_id) || empty($entity_type) || $entity_type === 'file') {
      return $entity_id;
    }

    try {
      // @phpstan-ignore-next-line
      $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
    }
    catch (PluginNotFoundException $e) {
      return $entity_id;
    }

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $storage->load($entity_id);
    if (!$entity instanceof EntityInterface) {
      return $entity_id;
    }

    if ($langcode !== '' &&
      $entity instanceof TranslatableInterface &&
      $entity->isTranslatable() &&
      $entity->hasTranslation($langcode)) {
      $entity = $entity->getTranslation($langcode);
    }

    try {
      $link = $entity->toLink($link_label, 'canonical', ['attributes' => ['target' => '_blank', 'rel' => 'noopener']]);
    }
    catch (\Exception $e) {
      return $link_label;
    }

    if (!$link instanceof Link) {
      return $link_label;
    }

    return $link->toRenderable();
  }

}
