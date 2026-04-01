<?php

namespace Drupal\italo_module\Plugin\views\field;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\views\Plugin\views\field\RenderedEntity;
use Drupal\views\ResultRow;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

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
    $entity = $values->_entity;
    if ($entity instanceof ParagraphInterface) {
      $node = $entity->getParentEntity();
    }
    elseif ($entity instanceof NodeInterface) {
      $node = $entity;
    }

    if (isset($node)) {
      try {
        $components = $node->get('field_components')->getValue();
      }
      catch (\Exception $e) {
        $components = [];
      }
      $entity = $this->getEntityTranslationByRelationship($node, $values);
      $build = [];
      $paragraph_id = $components[0]["target_id"] ?? NULL;
      if (!empty($paragraph_id)) {
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
        $access = $entity->access('view', NULL, TRUE);
        $build['#access'] = $access;
        if ($access->isAllowed()) {
          $view_builder = $this->entityTypeManager->getViewBuilder($this->getEntityTypeId());
          $build += $view_builder->view($new_entity, $this->options['view_mode'], $new_entity->language()
            ->getId());
        }

        $build["#cache"]["keys"] = [
          "entity_view",
          "paragraph",
          "leaflet_popup",
          $paragraph_id,
        ];
      }
    }
    return $build;
  }

}
