<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_users\Plugin\GraphQLCompose\FieldType;

use Drupal\graphql\GraphQL\Resolver\Composite;
use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeFieldTypeBase;
use Drupal\node\NodeTypeInterface;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeFieldType(
 *   id = "entity_owner",
 *   description = @Translation("The author of this entity."),
 *   type_sdl = "User",
 * )
 */
class EntityOwner extends GraphQLComposeFieldTypeBase {

  /**
   * {@inheritdoc}
   */
  public function getProducers(ResolverBuilder $builder): Composite {
    $entity_type = $this->getEntityWrapper()?->getEntity();

    // Hide field if type doesn't have display submitted enabled.
    if ($entity_type instanceof NodeTypeInterface) {
      if (!$entity_type->displaySubmitted()) {
        return $builder->compose($builder->fromValue(NULL));
      }
    }

    return $builder->compose(
      $builder->produce('entity_owner')
        ->map('entity', $builder->fromParent())
    );
  }

}
