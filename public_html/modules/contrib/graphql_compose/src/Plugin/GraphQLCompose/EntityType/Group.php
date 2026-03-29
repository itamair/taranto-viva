<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQLCompose\EntityType;

use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeEntityTypeBase;

/**
 * {@inheritdoc}
 *
 * @see https://www.drupal.org/project/group
 *
 * This is mostly a placeholder entity type.
 * Further works on the groups module is assumed in the future.
 * Probably via another contrib module.
 *
 * For the time being this entity type should allow simple views integration.
 *
 * @GraphQLComposeEntityType(
 *   id = "group",
 *   prefix = "Group",
 *   base_fields = {
 *     "langcode" = {},
 *     "path" = {},
 *     "created" = {},
 *     "changed" = {},
 *     "status" = {},
 *     "label" = {
 *       "name_sdl" = "name",
 *       "field_type" = "entity_label",
 *     },
 *   },
 * )
 */
class Group extends GraphQLComposeEntityTypeBase {

}
