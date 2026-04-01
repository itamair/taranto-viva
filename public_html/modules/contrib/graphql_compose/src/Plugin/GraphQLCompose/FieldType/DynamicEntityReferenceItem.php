<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQLCompose\FieldType;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\dynamic_entity_reference\Plugin\Field\FieldType\DynamicEntityReferenceItem as DynamicEntityReferenceItemBase;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeFieldType(
 *   id = "dynamic_entity_reference",
 * )
 */
class DynamicEntityReferenceItem extends EntityReferenceItem {

  /**
   * {@inheritdoc}
   *
   * Force to be non-generic to get a unique union type.
   */
  public function isGenericUnion(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getTypeSdl(): string {
    return $this->getUnionTypeSdl();
  }

  /**
   * {@inheritdoc}
   */
  protected function getUnionTargetTypes(FieldDefinitionInterface $field_definition): array {
    return DynamicEntityReferenceItemBase::getTargetTypes(
      $field_definition->getSettings()
    );
  }

}
