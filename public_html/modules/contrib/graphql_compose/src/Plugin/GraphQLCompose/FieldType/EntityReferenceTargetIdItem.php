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
 *   id = "entity_reference_target_id",
 *   type_sdl = "ID",
 * )
 */
class EntityReferenceTargetIdItem extends EntityReferenceItem {

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

    // Users select how they want to display ids.
    $expose_entity_ids = $this->configFactory
      ->get('graphql_compose.settings')
      ->get('settings.expose_entity_ids');

    // Get the entity reference target id.
    $composite->add(
      $builder->callback(fn (?array $entities) => array_map(
        fn (EntityInterface $entity) => $expose_entity_ids
          ? $entity->id()
          : $entity->uuid(),
        $entities ?: []
      ))
    );

    return $composite;
  }

}
