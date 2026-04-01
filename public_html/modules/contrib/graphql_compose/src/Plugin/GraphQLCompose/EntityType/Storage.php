<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQLCompose\EntityType;

use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeEntityTypeBase;

/**
 * {@inheritdoc}
 *
 * @see https://www.drupal.org/project/storage
 *
 * @GraphQLComposeEntityType(
 *   id = "storage",
 *   prefix = "Storage",
 *   base_fields = {
 *     "langcode" = {},
 *     "created" = {},
 *     "changed" = {},
 *     "published_at" = {},
 *     "status" = {},
 *     "name" = {
 *       "field_type" = "entity_label",
 *     },
 *   },
 * )
 */
class Storage extends GraphQLComposeEntityTypeBase {

}
