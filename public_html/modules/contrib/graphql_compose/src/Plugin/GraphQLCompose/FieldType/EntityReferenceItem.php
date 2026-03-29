<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQLCompose\FieldType;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\graphql\GraphQL\Resolver\Composite;
use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql_compose\Plugin\GraphQLCompose\FieldUnionInterface;
use Drupal\graphql_compose\Plugin\GraphQLCompose\FieldUnionTrait;
use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeFieldTypeBase;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeFieldType(
 *   id = "entity_reference",
 * )
 */
class EntityReferenceItem extends GraphQLComposeFieldTypeBase implements FieldUnionInterface {

  use FieldUnionTrait;

  /**
   * {@inheritdoc}
   */
  public function getProducers(ResolverBuilder $builder): Composite {

    $field_name = $this->getFieldName();

    $target_types = $this->getUnionTargetTypes(
      $this->getFieldDefinition()
    );

    return $builder->compose(
      $builder->produce('field_entity_reference')
        ->map('entity', $builder->fromParent())
        ->map('field', $builder->fromValue($field_name))
        ->map('types', $builder->fromValue($target_types))
        ->map('language', $builder->callback(
          fn (EntityInterface $entity) => ($entity instanceof TranslatableInterface)
            ? $entity->language()->getId()
            : NULL
        )),

      // Optionally remove any unpublished references.
      $builder->produce('entity_unpublished_filter')
        ->map('value', $builder->fromParent()),
    );
  }

}
