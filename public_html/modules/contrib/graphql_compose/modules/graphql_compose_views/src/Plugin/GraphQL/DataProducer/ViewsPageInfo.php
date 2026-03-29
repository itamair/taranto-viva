<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_views\Plugin\GraphQL\DataProducer;

use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Get pager info for a view.
 *
 * @DataProducer(
 *   id = "views_page_info",
 *   name = @Translation("Views page info"),
 *   description = @Translation("Metadata info on a view"),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("Page info results"),
 *   ),
 *   consumes = {
 *     "executable" = @ContextDefinition("any",
 *       label = @Translation("View executable"),
 *     ),
 *   },
 * )
 */
class ViewsPageInfo extends DataProducerPluginBase {

  /**
   * Resolve extra views pager information.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   View executable.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   The cache context.
   *
   * @return array
   *   View pager information.
   */
  public function resolve(ViewExecutable $view, FieldContext $context): array {
    return [
      'offset' => $view->getOffset() ?: 0,
      'page' => $view->getCurrentPage() ?: 0,
      'pageSize' => $view->getItemsPerPage() ?: 0,
      'total' => $view->total_rows ?: 0,
    ];
  }

}
