<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQL\SchemaExtension;

use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistryInterface;

/**
 * Adds Schema Types defined by the GraphQL Compose plugin system.
 *
 * @SchemaExtension(
 *   id = "graphql_compose_entity",
 *   name = "GraphQL Compose Entities",
 *   description = @Translation("GraphQL types defined by plugins."),
 *   schema = "graphql_compose",
 * )
 *
 * @internal
 */
class EntitySchemaExtension extends ResolverOnlySchemaExtensionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function registerResolvers(ResolverRegistryInterface $registry): void {
    $builder = new ResolverBuilder();

    // Resolve entity types and fields.
    foreach ($this->gqlEntityTypeManager->getPluginInstances() as $entity_type) {
      $entity_type->registerResolvers($registry, $builder);
    }

    // Utility for junk.
    $registry->addFieldResolver(
      'UnsupportedType',
      'unsupported',
      $builder->callback(fn () => TRUE),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseDefinition() {
    // Add entity types and fields to schema.
    foreach ($this->gqlEntityTypeManager->getPluginInstances() as $entity_type) {
      $entity_type->registerTypes();
    }

    return NULL;
  }

}
