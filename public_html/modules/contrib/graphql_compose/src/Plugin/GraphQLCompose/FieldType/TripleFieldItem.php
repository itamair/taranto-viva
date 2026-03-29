<?php

namespace Drupal\graphql_compose\Plugin\GraphQLCompose\FieldType;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use function Symfony\Component\String\u;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeFieldType(
 *   id = "triples_field",
 * )
 */
class TripleFieldItem extends DoubleFieldItem {

  /**
   * {@inheritdoc}
   */
  public function resolveFieldItem(FieldItemInterface $item, FieldContext $context) {
    return parent::resolveFieldItem($item, $context) + [
      'third' => $this->getSubField('third', $item, $context) ?: NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getTypeSdl(): string {
    $type = u('Triple');

    foreach (['first', 'second', 'third'] as $subfield) {
      $sub = $this->getSubfieldTypeSdl($subfield);
      $type = $type->append(u($sub)->title()->toString());
    }

    return $type->toString();
  }

}
