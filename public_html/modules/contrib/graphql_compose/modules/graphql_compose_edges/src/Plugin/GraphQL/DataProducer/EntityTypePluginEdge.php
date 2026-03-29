<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_edges\Plugin\GraphQL\DataProducer;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\graphql_compose_edges\EntityConnection;
use Drupal\graphql_compose_edges\EntityConnectionQueryHelper;
use Drupal\graphql_compose_edges\Filters\EntityFilterLanguage;
use Drupal\graphql_compose_edges\Filters\EntityFilterPublished;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Queries entities on the platform.
 *
 * @DataProducer(
 *   id = "graphql_compose_edges_entity_type",
 *   name = @Translation("Query a list of entity type"),
 *   description = @Translation("Loads the entity type entities."),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("EntityConnection"),
 *   ),
 *   consumes = {
 *     "first" = @ContextDefinition("integer",
 *       label = @Translation("First"),
 *       required = FALSE,
 *     ),
 *     "after" = @ContextDefinition("string",
 *       label = @Translation("After"),
 *       required = FALSE,
 *     ),
 *     "last" = @ContextDefinition("integer",
 *       label = @Translation("Last"),
 *       required = FALSE,
 *     ),
 *     "before" = @ContextDefinition("string",
 *       label = @Translation("Before"),
 *       required = FALSE,
 *     ),
 *     "reverse" = @ContextDefinition("boolean",
 *       label = @Translation("Reverse"),
 *       required = FALSE,
 *     ),
 *     "sortKey" = @ContextDefinition("string",
 *       label = @Translation("Sort key"),
 *       required = FALSE,
 *     ),
 *     "langcode" = @ContextDefinition("string",
 *       label = @Translation("Language code"),
 *       required = FALSE,
 *     ),
 *   },
 *   deriver = "Drupal\graphql_compose_edges\Plugin\Derivative\EntityTypePluginEdgeDeriver",
 * )
 */
class EntityTypePluginEdge extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );

    $instance->moduleHandler = $container->get('module_handler');

    return $instance;
  }

  /**
   * Resolves the request to the requested values.
   *
   * @param int|null $first
   *   Fetch the first X results.
   * @param string|null $after
   *   Cursor to fetch results after.
   * @param int|null $last
   *   Fetch the last X results.
   * @param string|null $before
   *   Cursor to fetch results before.
   * @param bool|null $reverse
   *   Reverses the order of the data.
   * @param string|null $sortKey
   *   Key to sort by.
   * @param string|null $langcode
   *   Language code to filter with.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   Cacheability context for this request.
   *
   * @return \Drupal\graphql_compose_edges\ConnectionInterface
   *   An entity connection with results and data about the paginated results.
   */
  public function resolve(?int $first, ?string $after, ?int $last, ?string $before, ?bool $reverse, ?string $sortKey, ?string $langcode, FieldContext $context) {

    [$entity_type, $bundle] = explode(':', $this->getDerivativeId());

    $helper = new EntityConnectionQueryHelper(
      $sortKey,
      $entity_type,
      $bundle,
    );

    // Set the default langcode to the current context language.
    $langcode = $langcode ?: $context->getContextLanguage();

    $connection = (new EntityConnection($helper))
      ->setPagination($first, $after, $last, $before, $reverse)
      ->setCacheContext($context);

    $connection->setFilter('published', NULL, EntityFilterPublished::class);
    $connection->setFilter('langcode', $langcode, EntityFilterLanguage::class);

    $this->moduleHandler->invokeAll('hook_graphql_compose_edges_alter', [
      $this,
      $connection,
      $context,
    ]);

    return $connection;
  }

}
