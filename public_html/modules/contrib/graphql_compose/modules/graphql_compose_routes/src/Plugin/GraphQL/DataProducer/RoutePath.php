<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_routes\Plugin\GraphQL\DataProducer;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Modifications to the route path.
 *
 * @DataProducer(
 *   id = "route_path",
 *   name = @Translation("Route path"),
 *   description = @Translation("Get the path for a route."),
 *   produces = @ContextDefinition("string",
 *     label = @Translation("A path"),
 *   ),
 *   consumes = {
 *     "path" = @ContextDefinition("string",
 *       label = @Translation("Path to resolve the route with"),
 *     ),
 *   },
 * )
 */
class RoutePath extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a \Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\path_alias\AliasManagerInterface $aliasManager
   *   Drupal alias manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Drupal module handler.
   * @param \Symfony\Component\HttpFoundation\Request $currentRequest
   *   Drupal current request.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected AliasManagerInterface $aliasManager,
    protected ModuleHandlerInterface $moduleHandler,
    protected Request $currentRequest,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('path_alias.manager'),
      $container->get('module_handler'),
      $container->get('request_stack')->getCurrentRequest(),
    );
  }

  /**
   * Resolve the desired path from a user provided string.
   *
   * @param string|null $path
   *   The path to check.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   Cache context.
   *
   * @return string|null
   *   The path to use for route resolution.
   */
  public function resolve(?string $path, FieldContext $context): ?string {
    // Give opportunity for other modules to alter incoming path urls.
    $this->moduleHandler->invokeAll('graphql_compose_routes_incoming_alter', [&$path, $context]);

    if (!$path) {
      return NULL;
    }

    // A subdirectory multi-site installation path string including directory
    // will not be handled correctly by path validator.
    $base_path = $this->currentRequest->getBasePath();
    if ($base_path && strpos($path, $base_path) === 0) {
      $path = substr($path, strlen($base_path));
    }

    // Check the aliases by language.
    if (str_starts_with($path, '/')) {
      $path = $this->aliasManager->getPathByAlias($path);
    }

    return $path;
  }

}
