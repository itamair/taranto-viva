<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQLCompose\EntityType;

use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeEntityTypeBase;

/**
 * {@inheritdoc}
 *
 * @see https://www.drupal.org/project/paragraphs
 *
 * @GraphQLComposeEntityType(
 *   id = "paragraphs_library_item",
 *   base_fields = {
 *     "created" = {},
 *     "changed" = {},
 *     "langcode" = {},
 *     "status" = {},
 *     "label" = {
 *       "field_type" = "entity_label",
 *     },
 *     "paragraphs" = {
 *       "field_type" = "entity_reference_revisions",
 *     },
 *   },
 * )
 */
class LibraryItem extends GraphQLComposeEntityTypeBase {

}
