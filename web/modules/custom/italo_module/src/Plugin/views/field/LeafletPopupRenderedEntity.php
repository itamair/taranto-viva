<?php

namespace Drupal\italo_module\Plugin\views\field;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\views\Plugin\views\field\RenderedEntity;
use Drupal\views\ResultRow;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Provides a field handler which renders the entity for the Leaflet Popup.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("leaflet_popup_rendered_entity")
 */
class LeafletPopupRenderedEntity extends RenderedEntity implements CacheableDependencyInterface {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = Node::load($values->nid);
    try {
      $components = $entity->get('field_components')->getValue();
    }
    catch (\Exception $e) {
      $components = [];
    }
    $entity = $this->getEntityTranslationByRelationship($entity, $values);
    $paragraph_id = $values->paragraphs_item_field_data_node__field_components_id_1;
    $paragraph = Paragraph::load($paragraph_id);
    $new_entity = clone($entity);
    if ($paragraph->bundle() == 'geoimage') {
      $paragraph_id_component = [];
      foreach ($components as $component) {
        if ($component['target_id'] === $paragraph_id) {
          $paragraph_id_component = $component;
          break;
        }
      }
      if (!empty($paragraph_id_component) && $entity->bundle() === 'territorial_report') {
        $new_entity = $new_entity->set('field_components', $paragraph_id_component);
      }
    }
    $build = [];
    $access = $entity->access('view', NULL, TRUE);
    $build['#access'] = $access;
    if ($access->isAllowed()) {
      $view_builder = $this->entityTypeManager->getViewBuilder($this->getEntityTypeId());
      $build += $view_builder->view($new_entity, $this->options['view_mode'], $new_entity->language()->getId());
    }

    $build["#cache"]["keys"] = [
      "entity_view",
      "paragraph",
      "leaflet_popup",
      $paragraph_id,
    ];
    return $build;
  }

}
