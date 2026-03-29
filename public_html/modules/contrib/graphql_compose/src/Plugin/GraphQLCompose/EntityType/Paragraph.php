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
 *   id = "paragraph",
 *   prefix = "Paragraph",
 *   base_fields = {
 *     "created" = {},
 *     "changed" = {},
 *     "langcode" = {},
 *     "status" = {},
 *   },
 * )
 */
class Paragraph extends GraphQLComposeEntityTypeBase {

}
