<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQLCompose\SchemaType;

use Drupal\graphql_compose\Plugin\GraphQLCompose\FieldType\TripleFieldItem;
use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeSchemaTypeBase;
use GraphQL\Type\Definition\ObjectType;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeSchemaType(
 *   id = "TripleField",
 * )
 */
class TripleFieldType extends GraphQLComposeSchemaTypeBase {

  /**
   * {@inheritdoc}
   */
  public function getTypes(): array {
    $types = [];

    if (!$this->moduleHandler->moduleExists('triples_field')) {
      return $types;
    }

    // Find all exposed triple fields.
    // This assumes all fields have been bootstrapped.
    $fields = $this->gqlFieldTypeManager->getFields();

    array_walk_recursive($fields, function ($field) use (&$types) {
      if ($field instanceof TripleFieldItem) {

        $types[$field->getTypeSdl()] = new ObjectType([
          'name' => $field->getTypeSdl(),
          'description' => (string) $this->t('A triple field is a specialty field provided by the CMS.'),
          'fields' => fn() => [
            'first' => [
              'type' => static::type($field->getSubfieldTypeSdl('first')),
              'description' => (string) $this->t('The first value of the triple field.'),
            ],
            'second' => [
              'type' => static::type($field->getSubfieldTypeSdl('second')),
              'description' => (string) $this->t('The second value of the triple field.'),
            ],
            'third' => [
              'type' => static::type($field->getSubfieldTypeSdl('third')),
              'description' => (string) $this->t('The third value of the triple field.'),
            ],
          ],
        ]);
      }
    });

    return array_values($types);
  }

}
