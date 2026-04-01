<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_edges\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\graphql_compose\Plugin\GraphQLComposeEntityTypeManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derivatives of entity.
 */
class EntityTypePluginEdgeDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * Constructs a new EntityTypePluginEdgeDeriver.
   *
   * @param \Drupal\graphql_compose\Plugin\GraphQLComposeEntityTypeManager $gqlEntityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected GraphQLComposeEntityTypeManager $gqlEntityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('graphql_compose.entity_type_manager'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * Return a deriver with a pattern of PLUGIN:ENTITYTYPE:BUNDLE.
   */
  public function getDerivativeDefinitions($base_plugin_definition) {

    $entity_type_plugins = $this->gqlEntityTypeManager->getPluginInstances();

    foreach ($entity_type_plugins as $entity_type_id => $entity_type_plugin) {
      foreach (array_keys($entity_type_plugin->getBundles()) as $bundle_id) {
        $this->derivatives[$entity_type_id . ':' . $bundle_id] = $base_plugin_definition;
      }
    }

    return $this->derivatives;
  }

}
