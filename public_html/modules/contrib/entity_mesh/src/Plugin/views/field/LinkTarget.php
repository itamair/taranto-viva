<?php

namespace Drupal\entity_mesh\Plugin\views\field;

use Drupal\views\ResultRow;

/**
 * Provides a custom field for a specific column.
 *
 * @ViewsField("entity_mesh_link_target")
 */
class LinkTarget extends BaseLinkSource {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity_type = $values->entity_mesh_target_entity_type ?? '';
    $entity_id = $values->entity_mesh_target_entity_id ?? '';
    $langcode = $values->entity_mesh_target_entity_langcode ?? '';
    return $this->generateLink('Entity link', $entity_type, $entity_id, $langcode);
  }

}
