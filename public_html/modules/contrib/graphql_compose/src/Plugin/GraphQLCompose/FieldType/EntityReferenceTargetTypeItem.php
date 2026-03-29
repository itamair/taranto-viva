<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQLCompose\FieldType;

use Drupal\Core\Entity\EntityInterface;
use Drupal\graphql\GraphQL\Resolver\Composite;
use Drupal\graphql\GraphQL\ResolverBuilder;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeFieldType(
 *   id = "entity_reference_target_type",
 *   type_sdl = "String",
 * )
 */
class EntityReferenceTargetTypeItem extends EntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  public function isSingleUnion(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getProducers(ResolverBuilder $builder): Composite {
    // Use the parent producers to use the buffers for entity loading.
    $composite = parent::getProducers($builder);

    // Get the entity reference target id.
    $composite->add(
      $builder->callback(fn (?array $entities) => array_map(
        fn (EntityInterface $entity) => $entity->getEntityTypeId(),
        $entities ?: []
      ))
    );

    return $composite;
  }

}
