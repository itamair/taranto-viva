<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_views\Plugin\GraphQL\SchemaExtension;

use Drupal\Core\Entity\EntityInterface;
use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistryInterface;
use Drupal\graphql_compose\Plugin\GraphQL\SchemaExtension\ResolverOnlySchemaExtensionPluginBase;
use Drupal\graphql_compose_views\Plugin\views\display\GraphQL;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use GraphQL\Error\UserError;

/**
 * Add view resolution.
 *
 * @SchemaExtension(
 *   id = "graphql_compose_views",
 *   name = "GraphQL Compose Views",
 *   description = @Translation("Exposed views resolution."),
 *   schema = "graphql_compose",
 * )
 */
class ViewsSchemaExtension extends ResolverOnlySchemaExtensionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function registerResolvers(ResolverRegistryInterface $registry): void {
    $builder = new ResolverBuilder();

    $viewStorage = $this->entityTypeManager->getStorage('view');

    // The parent is a ViewExecutable.
    $registry->addFieldResolver(
      'View',
      'id',
      $builder->callback(function (ViewExecutable $executable) {
        return $executable->storage->uuid();
      }),
    );

    $registry->addFieldResolver(
      'View',
      'view',
      $builder->callback(function (ViewExecutable $executable) {
        return $executable->storage->id();
      }),
    );

    $registry->addFieldResolver(
      'View',
      'display',
      $builder->callback(function (ViewExecutable $executable) {
        return $executable->current_display;
      }),
    );

    $registry->addFieldResolver(
      'View',
      'langcode',
      $builder->callback(function (ViewExecutable $executable) {
        return $executable->storage->language()->getId();
      }),
    );

    $registry->addFieldResolver(
      'View',
      'label',
      $builder->callback(function (ViewExecutable $executable) {
        return $executable->storage->label();
      }),
    );

    $registry->addFieldResolver(
      'View',
      'description',
      $builder->callback(function (ViewExecutable $executable) {
        return $executable->storage->get('description');
      }),
    );

    $registry->addFieldResolver(
      'View',
      'pageInfo',
      $builder->produce('views_page_info')
        ->map('executable', $builder->fromParent())
    );

    foreach (Views::getApplicableViews('graphql_display') as $applicable_view) {
      // Destructure view and display ids.
      [$view_id, $display_id] = $applicable_view;

      if (!$view_entity = $viewStorage->load($view_id)) {
        continue;
      }

      /** @var \Drupal\views\ViewEntityInterface|null $view_entity */
      $view = $view_entity->getExecutable();
      $view->setDisplay($display_id);
      $view->initHandlers();

      /** @var \Drupal\graphql_compose_views\Plugin\views\display\GraphQL $display */
      $display = $view->getDisplay();

      // The parent is a ViewExecutable.
      $registry->addFieldResolver(
        'Query',
        $display->getGraphQlQueryName(),
        $builder->produce('views_executable')
          ->map('view_id', $builder->fromValue($view_id))
          ->map('display_id', $builder->fromValue($display_id))
          ->map('page', $builder->fromArgument('page'))
          ->map('page_size', $builder->fromArgument('pageSize'))
          ->map('offset', $builder->fromArgument('offset'))
          ->map('filter', $builder->fromArgument('filter'))
          ->map('contextual_filter', $builder->fromArgument('contextualFilter'))
          ->map('sort_key', $builder->fromArgument('sortKey'))
          ->map('sort_dir', $builder->fromArgument('sortDir'))
      );

      $registry->addFieldResolver(
        $display->getGraphQlResultName(),
        'results',
        $builder->produce('views_entity_results')
          ->map('executable', $builder->fromParent())
      );

      if ($display->usesExposed()) {
        $registry->addFieldResolver(
          $display->getGraphQlResultName(),
          'filters',
          $builder->produce('views_filters')
            ->map('executable', $builder->fromParent())
        );
      }

      // Add union type for the view result, if required.
      if ($display->hasGraphQlUnionTypes()) {
        $union_types = $display->getGraphQlUnionTypes();

        $registry->addTypeResolver(
          $display->getGraphQlRowName(),
          function (EntityInterface $value) use ($union_types) {
            $entity_type_id = $value->getEntityTypeId();
            $entity_bundle_id = $value->bundle();

            $type_sdl = $this->gqlEntityTypeManager
              ->getPluginInstance($entity_type_id)
              ?->getBundle($entity_bundle_id)
              ?->getTypeSdl();

            if (!in_array($type_sdl, $union_types)) {
              throw new UserError(sprintf('Entity type %s::%s is not enabled within GraphQL. Adjust the filters on this view to enabled entity types only.', $entity_type_id, $entity_bundle_id));
            }

            return $type_sdl;
          }
        );
      }

      $view->destroy();
    }

    $registry->addTypeResolver(
      'ViewResultUnion',
      function ($view) {
        if ($view instanceof ViewExecutable) {
          $display = $view->getDisplay();
          $display_id = $display->display['id'];

          if (!$display instanceof GraphQL) {
            throw new UserError(sprintf('View %s:%s is not a GraphQL display.', $view->id(), $display_id));
          }

          return $display->getGraphQlResultName();
        }
        throw new UserError('Could not resolve view type.');
      }
    );
  }

}
