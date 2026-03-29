<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQLCompose\FieldType;

use Drupal\Core\Entity\EntityInterface;
use Drupal\graphql\GraphQL\Resolver\Composite;
use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeFieldTypeBase;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeFieldType(
 *   id = "path",
 *   type_sdl = "String",
 * )
 */
class PathItem extends GraphQLComposeFieldTypeBase {

  /**
   * {@inheritdoc}
   *
   * Paths cannot be generated if the entity is new.
   */
  public function isRequired(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getProducers(ResolverBuilder $builder): Composite {
    return $builder->compose(
      $builder->cond([
        [
          $builder->callback(fn (EntityInterface $entity) => $entity->isNew()),
          $builder->fromValue(NULL),
        ], [
          $builder->fromValue(TRUE),
          $builder->compose(
            $builder->produce('entity_url')
              ->map('entity', $builder->fromParent()),

            $builder->produce('url_path')
              ->map('url', $builder->fromParent()),
          ),
        ],
      ]),
    );
  }

}
