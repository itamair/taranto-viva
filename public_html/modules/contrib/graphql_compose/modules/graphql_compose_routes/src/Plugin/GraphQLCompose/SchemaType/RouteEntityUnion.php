<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_routes\Plugin\GraphQLCompose\SchemaType;

use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeSchemaTypeBase;
use Drupal\graphql_compose\Wrapper\EntityTypeWrapper;
use GraphQL\Type\Definition\UnionType;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeSchemaType(
 *   id = "RouteEntityUnion",
 * )
 */
class RouteEntityUnion extends GraphQLComposeSchemaTypeBase {

  /**
   * {@inheritdoc}
   */
  public function getTypes(): array {
    $types = [];

    $union_types = array_map(
      fn(EntityTypeWrapper $bundle): string => $bundle->getTypeSdl(),
      $this->getUnionBundles()
    );

    $types[] = new UnionType([
      'name' => $this->getPluginId(),
      'description' => (string) $this->t('A list of possible entities that can be returned by URL.'),
      'types' => fn() => array_map(
        static::type(...),
        $union_types ?: ['UnsupportedType']
      ),
    ]);

    return $types;
  }

  /**
   * Return all type bundles that have a path field.
   *
   * @return \Drupal\graphql_compose\Wrapper\EntityTypeWrapper[]
   *   Enabled bundles.
   */
  private function getUnionBundles(): array {
    $result = [];

    foreach ($this->gqlEntityTypeManager->getPluginInstances() as $entity_type) {

      foreach ($entity_type->getBundles() as $bundle) {
        if ($bundle->getSetting('routes_enabled')) {
          $result[] = $bundle;
        }
      }
    }

    return $result;
  }

}
