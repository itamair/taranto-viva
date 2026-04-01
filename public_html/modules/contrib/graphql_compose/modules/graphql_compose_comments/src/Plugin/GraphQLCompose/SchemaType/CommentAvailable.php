<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_comments\Plugin\GraphQLCompose\SchemaType;

use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeSchemaTypeBase;
use Drupal\graphql_compose_comments\CommentableTrait;
use GraphQL\Type\Definition\EnumType;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeSchemaType(
 *   id = "CommentAvailable",
 * )
 */
class CommentAvailable extends GraphQLComposeSchemaTypeBase {

  use CommentableTrait;

  /**
   * {@inheritdoc}
   */
  public function getTypes(): array {
    $types = [];

    $bundles = $this->getAllCommentableBundles();

    $values = [];
    foreach ($bundles as $bundle) {
      $bundle_type_sdl = $bundle->getTypeSdl();

      $values[$bundle_type_sdl] = [
        'value' => $bundle->getEntityType()->id() . ':' . $bundle->getEntity()->id(),
        'description' => $bundle->getDescription(),
      ];
    }

    $undefined = [
      'UNDEFINED' => [
        'value' => 'undefined',
        'description' => (string) $this->t('No types have comments enabled.'),
      ],
    ];

    $types[] = new EnumType([
      'name' => $this->getPluginId(),
      'description' => (string) $this->t('List of types that have comments available.'),
      'values' => $values ?: $undefined,
    ]);

    return $types;
  }

}
