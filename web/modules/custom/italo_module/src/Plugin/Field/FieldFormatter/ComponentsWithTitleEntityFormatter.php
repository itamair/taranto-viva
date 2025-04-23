<?php

namespace Drupal\italo_module\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\entity_reference_revisions\Plugin\Field\FieldFormatter\EntityReferenceRevisionsEntityFormatter;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Plugin implementation of the 'Leaflet Popup Components' formatter.
 *
 * @FieldFormatter(
 *   id = "components_with_title_entity_view",
 *   label = @Translation("Components with Title"),
 *   description = @Translation("Display the Components Field with parent Entity Title."),
 *   field_types = {
 *     "entity_reference_revisions"
 *   }
 * )
 */
class ComponentsWithTitleEntityFormatter extends EntityReferenceRevisionsEntityFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $view_mode = $this->getSetting('view_mode');
    $elements = [];

    $locations_limit_reached = FALSE;
    $images_limit_reached = FALSE;

    $parent_entity = NULL;

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {

      // Protect ourselves from recursive rendering.
      static $depth = 0;
      $depth++;
      if ($depth > 20) {
        $this->loggerFactory->get('entity')->error(
          'Recursive rendering detected when rendering entity
          @entity_type @entity_id. Aborting rendering.', [
            '@entity_type' => $entity->getEntityTypeId(),
            '@entity_id' => $entity->id(),
          ]);
        return $elements;
      }
      if ($entity instanceof Paragraph &&
        in_array($entity->bundle(), ['image', 'location', 'geoimage'])
      ) {
        /** @var \Drupal\paragraphs\Entity\Paragraph $entity */
        if (!isset($parent_entity)) {
          $parent_entity = $entity->getParentEntity();
        }

        if (in_array($entity->bundle(), ['image', 'geoimage'])) {
          if ($images_limit_reached) {
            continue;
          }
          else {
            $images_limit_reached = TRUE;
          }
        }
        if ($entity->bundle() == ['location']) {
          if ($locations_limit_reached) {
            continue;
          }
          else {
            $locations_limit_reached = TRUE;
          }
        }
        $view_builder = \Drupal::entityTypeManager()->getViewBuilder($entity->getEntityTypeId());

        // Set image or geoimage always first in the order.
        if (in_array($entity->bundle(), ['image', 'geoimage'])) {
          array_unshift($elements, $view_builder->view($entity, $view_mode, $entity->language()->getId()));
        }
        else {
          $elements[$delta] = $view_builder->view($entity, $view_mode, $entity->language()->getId());
        }

        // Add a resource attribute to set the mapping property's value to the
        // entity's url. Since we don't know what the markup of the entity will
        // be, we shouldn't rely on it for structured data such as RDFa.
        if (!empty($items[$delta]->_attributes) && !$entity->isNew() && $entity->hasLinkTemplate('canonical')) {
          $items[$delta]->_attributes += ['resource' => $entity->toUrl()->toString()];
        }
        $depth = 0;
      }
      elseif ($locations_limit_reached && $images_limit_reached) {
        break;
      }
    }

    if (isset($parent_entity)) {
      $title_array = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $parent_entity->label(),
        '#cache' => ['tags' => $parent_entity->getCacheTags()],
      ];
      $elements_titled = array_merge(array_splice($elements, 0, 1, TRUE), [$title_array], array_splice($elements, 1, 2, TRUE));
    }

    return [
      '#type' => 'link',
      '#title' => [
        $elements_titled ?? '',
      ],
      '#url' => $parent_entity ? $parent_entity->toUrl() : NULL,
      '#attributes' => [
        'class' => 'node_title',
      ],
    ];
  }

}
