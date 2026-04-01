<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_comments;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\graphql_compose\Plugin\GraphQLComposeFieldTypeManager;
use Drupal\graphql_compose\Wrapper\EntityTypeWrapper;
use GraphQL\Type\Definition\Type;

use function Symfony\Component\String\u;

/**
 * Trait for getting commentable fields and types.
 */
trait CommentableTrait {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The GraphQL Compose Field Type Manager.
   *
   * @var \Drupal\graphql_compose\Plugin\GraphQLComposeFieldTypeManager
   */
  protected GraphQLComposeFieldTypeManager $gqlFieldTypeManager;

  /**
   * Get the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected function getEntityTypeManager(): EntityTypeManagerInterface {
    return $this->entityTypeManager ??= \Drupal::service('entity_type.manager');
  }

  /**
   * Get the entity field manager.
   *
   * @return \Drupal\Core\Entity\EntityFieldManagerInterface
   *   The entity field manager.
   */
  protected function getEntityFieldManager(): EntityFieldManagerInterface {
    return $this->entityFieldManager ??= \Drupal::service('entity_field.manager');
  }

  /**
   * Get the GraphQL Compose Field Type Manager.
   *
   * @return \Drupal\graphql_compose\Plugin\GraphQLComposeFieldTypeManager
   *   The GraphQL Compose Field Type Manager.
   */
  protected function getGqlFieldTypeManager(): GraphQLComposeFieldTypeManager {
    return $this->gqlFieldTypeManager ??= \Drupal::service('graphql_compose.field_type_manager');
  }

  /**
   * Name of the mutation for an input bundle.
   *
   * @param \Drupal\graphql_compose\Wrapper\EntityTypeWrapper $bundle
   *   The bundle to get the mutation name for.
   *
   * @return string
   *   The mutation name.
   */
  public function getMutationNameSdl(EntityTypeWrapper $bundle): string {
    return u($bundle->getNameSdl())
      ->title()
      ->prepend('add')
      ->toString();
  }

  /**
   * Name of the type for a comment input.
   *
   * @param \Drupal\graphql_compose\Wrapper\EntityTypeWrapper $bundle
   *   The bundle to get the mutation input name for.
   *
   * @return string
   *   The mutation input name.
   */
  public function getMutationInputNameSdl(EntityTypeWrapper $bundle): string {
    return u($bundle->getTypeSdl())
      ->title()
      ->append('Input')
      ->toString();
  }

  /**
   * Get all fields that are a comment type.
   *
   * @return \Drupal\field\FieldConfigInterface[]
   *   Field config instances for comment fields.
   */
  public function getAllCommentableFields(): array {
    return $this->getEntityTypeManager()
      ->getStorage('field_config')
      ->loadByProperties(['field_type' => 'comment']);
  }

  /**
   * Get all SDL Types that have comments enabled on them.
   *
   * @return \Drupal\graphql_compose\Wrapper\EntityTypeWrapper[]
   *   Array of entity type wrappers.
   */
  public function getAllCommentableBundles(): array {
    $bundle_types = [];

    $fields = $this->getAllCommentableFields();

    foreach ($fields as $field) {
      $bundle_fields = $this->getGqlFieldTypeManager()->getBundleFields(
        $field->getTargetEntityTypeId(),
        $field->getTargetBundle()
      );

      if (!$field_plugin = $bundle_fields[$field->getName()] ?? NULL) {
        continue;
      }

      $bundle = $field_plugin->getEntityWrapper();
      $bundle_types[$bundle->getEntity()->id()] = $bundle;
    }

    return $bundle_types;
  }

  /**
   * Get input fields unique to a comment.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle_id
   *   The bundle id.
   *
   * @return array
   *   Array of input fields.
   */
  public function getInputFields($entity_type_id, $bundle_id): array {
    $fields = [];
    $form_fields = EntityFormDisplay::load($entity_type_id . '.' . $bundle_id . '.default')->getComponents();
    $field_definitions = $this->getEntityFieldManager()->getFieldDefinitions($entity_type_id, $bundle_id);

    foreach (array_keys($form_fields) as $field_name) {
      $field_definition = $field_definitions[$field_name] ?? NULL;

      if ($field_definition) {
        // @todo should this be the same as the read fields?
        // that assumes it's enabled. but its possible.
        $name = u($field_name)->camel()->toString();

        $type = $this->getInputFieldTypeSdl($field_definition);

        if ($field_definition->getFieldStorageDefinition()->isMultiple()) {
          $type = Type::listOf($type);
        }

        if ($field_definition->isRequired()) {
          $type = Type::nonNull($type);
        }

        $fields[$name] = [
          'type' => $type,
          'description' => (string) ($field_definition->getDescription() ?: $field_definition->getLabel()),
          'definition' => $field_definition,
        ];
      }
    }

    ksort($fields);

    return $fields;
  }

  /**
   * Utility function to get the sdl type from field settings.
   *
   * We only return primitive scalars.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   *
   * @return \GraphQL\Type\Definition\Type
   *   The GraphQL type.
   */
  public function getInputFieldTypeSdl(FieldDefinitionInterface $field_definition): Type {
    switch ($field_definition->getType()) {
      case 'boolean':
        return Type::boolean();

      case 'changed':
      case 'created':
      case 'integer':
      case 'timestamp':
        return Type::int();

      case 'decimal':
      case 'float':
        return Type::float();

      case 'uuid':
        return Type::id();

      default:
        return Type::string();
    }
  }

}
