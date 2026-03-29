<?php

namespace Drupal\entity_mesh\Plugin\views\field;

use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Provides a link to the entity_mesh view filtered by category and target_host.
 *
 * @ViewsField("entity_mesh_filtered_view_link")
 */
class FilteredViewLink extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $category = $values->entity_mesh_category ?? '';
    $target_host = $values->entity_mesh_target_host ?? '';

    if (empty($category) && empty($target_host)) {
      return $this->t('View all');
    }

    $query = [];
    if (!empty($category)) {
      $query['category[]'] = $category;
    }
    if (!empty($target_host)) {
      $query['target_host'] = $target_host;
    }

    $url = Url::fromRoute('view.entity_mesh.table', [], ['query' => $query]);

    $link_text = $this->t('View');

    return [
      '#type' => 'link',
      '#title' => $link_text,
      '#url' => $url,
      '#attributes' => [
        'target' => '_blank',
        'rel' => 'noopener',
      ],
    ];
  }

}
