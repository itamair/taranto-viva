<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_eck\Plugin\GraphQLCompose\EntityType;

use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeEntityTypeBase;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeEntityType(
 *   id = "eck",
 *   deriver = "\Drupal\graphql_compose_eck\Plugin\Derivative\EckEntityTypeDeriver",
 * )
 */
class Eck extends GraphQLComposeEntityTypeBase {

}
