<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQLCompose\SchemaType;

use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeSchemaTypeBase;
use GraphQL\Type\Definition\CustomScalarType;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeSchemaType(
 *   id = "UntypedStructuredData",
 * )
 */
class UntypedStructuredDataType extends GraphQLComposeSchemaTypeBase {

  /**
   * {@inheritdoc}
   */
  public function getTypes(): array {
    $types = [];

    $types[] = new CustomScalarType([
      'name' => $this->getPluginId(),
      'description' => (string) $this->t('Untyped structured data. Eg JSON, configuration, settings, attributes.'),
    ]);

    return $types;
  }

}
