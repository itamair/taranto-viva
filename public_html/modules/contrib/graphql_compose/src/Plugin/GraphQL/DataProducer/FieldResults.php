<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQL\DataProducer;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeFieldTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Allow other modules to hook the results.
 *
 * @DataProducer(
 *   id = "field_results",
 *   name = @Translation("Field plugin resolver results"),
 *   description = @Translation("Allow late modification of field plugin results."),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("Final field results"),
 *   ),
 *   consumes = {
 *     "plugin" = @ContextDefinition("any",
 *       label = @Translation("Field plugin instance"),
 *     ),
 *     "value" = @ContextDefinition("any",
 *       label = @Translation("Field results to alter"),
 *     ),
 *     "entity" = @ContextDefinition("any",
 *       label = @Translation("Parent entity"),
 *     ),
 *   },
 * )
 */
class FieldResults extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The module handler service.
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
   * Allow other modules to hook the results.
   *
   * @param \Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeFieldTypeInterface $plugin
   *   The field plugin being processed.
   * @param mixed $results
   *   The results from a field plugin type to process.
   * @param mixed $entity
   *   The entity being processed.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   The cache and current context.
   *
   * @return mixed
   *   Results from resolution. Array for multiple.
   */
  public function resolve(GraphQLComposeFieldTypeInterface $plugin, $results, $entity, FieldContext $context) {

    $results = is_array($results) ? $results : [$results];

    $this->moduleHandler->invokeAll('graphql_compose_field_results_alter', [
      &$results,
      $entity,
      $plugin,
      $context,
    ]);

    // How did you get here?
    // Probably via permissions removing content from a required field.
    // This can NEVER be null.
    // https://www.drupal.org/project/graphql_compose/issues/3408161
    if (empty($results) && $plugin->isRequired() && !$plugin->isMultiple()) {
      throw new \Exception(sprintf(
        'Required single field plugin returned no results. Field: %s, Entity Type: %s, Entity ID: %s',
        $plugin->getFieldName(),
        $entity?->getEntityTypeId() ?: 'Unknown',
        $entity?->id() ?: 'Unknown',
      ));
    }

    // Null out empty results.
    if (empty($results) && !$plugin->isRequired()) {
      return NULL;
    }

    return $plugin->isMultiple() ? $results : reset($results);
  }

}
