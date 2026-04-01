<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_views\Plugin\GraphQL\DataProducer;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Load view results.
 *
 * @DataProducer(
 *   id = "views_entity_results",
 *   name = @Translation("Views entity results"),
 *   description = @Translation("Entity results for a view"),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("Views results"),
 *   ),
 *   consumes = {
 *     "executable" = @ContextDefinition("any",
 *       label = @Translation("View executable"),
 *     ),
 *   },
 * )
 */
class ViewsEntityResults extends DataProducerPluginBase {

  /**
   * Resolve view entity results.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   View executable.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   The cache context.
   *
   * @return array
   *   View rows data.
   */
  public function resolve(ViewExecutable $view, FieldContext $context): array {

    $results = $view->render() ?? [];

    /** @var \Drupal\graphql\GraphQL\Execution\FieldContext $cache */
    $cache = CacheableMetadata::createFromRenderArray($results);
    $context->addCacheableDependency($cache);

    // @todo figure out what to do with unsupported entity types in results.
    // My initial thinking is an exception is reasonable here.
    // If the type isn't exposed and someone goes and makes a view trying
    // to expose it, well... Feels like an error to me.
    return $results['#rows'] ?? [];
  }

}
