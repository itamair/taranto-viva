<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQLCompose\FieldType;

use Drupal\graphql\GraphQL\Resolver\Composite;
use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeFieldTypeBase;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeFieldType(
 *   id = "entity_label",
 *   type_sdl = "String",
 * )
 */
class EntityLabelItem extends GraphQLComposeFieldTypeBase {

  /**
   * {@inheritdoc}
   */
  public function getProducers(ResolverBuilder $builder): Composite {
    return $builder->compose(
      $builder->produce('entity_label')
        ->map('entity', $builder->fromParent()),
    );
  }

}
