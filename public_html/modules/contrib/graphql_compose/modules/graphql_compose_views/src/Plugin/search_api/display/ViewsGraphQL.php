<?php

namespace Drupal\graphql_compose_views\Plugin\search_api\display;

use Drupal\search_api\Plugin\search_api\display\ViewsDisplayBase;

/**
 * Represents a Views GraphQL display.
 *
 * @SearchApiDisplay(
 *   id = "views_graphql",
 *   views_display_type = "graphql",
 *   deriver = "Drupal\search_api\Plugin\search_api\display\ViewsDisplayDeriver",
 * )
 */
class ViewsGraphQL extends ViewsDisplayBase {

  /**
   * {@inheritdoc}
   */
  public function isRenderedInCurrentRequest(): bool {
    $plugin_definition = $this->getPluginDefinition();

    $view_id = $plugin_definition['view_id'];
    $display_id = $plugin_definition['view_display'];

    $executed = \Drupal::request()->attributes->get('_graphql_views', []);
    return in_array($view_id . ':' . $display_id, $executed);
  }

}
