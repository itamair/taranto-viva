<?php

/**
 * @file
 * Hooks provided by GraphQL Compose module.
 */

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeEntityTypeInterface;
use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeFieldTypeInterface;
use Drupal\graphql_compose\Plugin\GraphQLComposeSchemaTypeManager;

/**
 * Add custom types to the schema.
 *
 * @param Drupal\graphql_compose\Plugin\GraphQLComposeSchemaTypeManager $manager
 *   The GraphQL Compose Schema Type Manager.
 */
function hook_graphql_compose_print_types(GraphQLComposeSchemaTypeManager $manager): void {
  $my_type = new \GraphQL\Type\Definition\ObjectType([
    'name' => 'MyType',
    'fields' => [
      'id' => \GraphQL\Type\Definition\Type::string(),
    ],
  ]);
  $manager->add($my_type);
}

/**
 * Add extensions to the schema.
 *
 * @param Drupal\graphql_compose\Plugin\GraphQLComposeSchemaTypeManager $manager
 *   The GraphQL Compose Schema Type Manager.
 */
function hook_graphql_compose_print_extensions(GraphQLComposeSchemaTypeManager $manager): void {
  $my_extension = new \GraphQL\Type\Definition\ObjectType([
    'name' => 'Query',
    'fields' => fn() => [
      'thing' => [
        'type' => $manager->get('MyType'),
        'description' => (string) t('Get my type'),
      ],
    ],
  ]);
  $manager->extend($my_extension);
}

/**
 * Alter the result from language singularize.
 *
 * @param string $original
 *   Original bundle string to be converted.
 * @param string $singular
 *   Result from the language inflector interface.
 */
function hook_graphql_compose_singularize_alter($original, string &$singular): void {
  if ($original === 'tags') {
    $singular = 'tog';
  }
}

/**
 * Alter the result from language pluralize.
 *
 * @param string $singular
 *   Singular bundle string to be converted.
 * @param string $plural
 *   Result from the language inflector interface.
 */
function hook_graphql_compose_pluralize_alter($singular, string &$plural): void {
  if ($singular === 'tog') {
    $plural = 'tugs';
  }
}

/**
 * Change enabled state of a field.
 *
 * @param bool $enabled
 *   Field is enabled or not.
 * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
 *   Field definition.
 */
function hook_graphql_compose_field_enabled_alter(bool &$enabled, FieldDefinitionInterface $field_definition) {
  $entity_type = $field_definition->getTargetEntityTypeId();

  if ($entity_type === 'user' && $field_definition->getName() === 'mail') {
    $enabled = FALSE;
  }
}

/**
 * Alter results for GraphQL Compose producers.
 *
 * @param array $results
 *   The results being returned.
 * @param mixed $entity
 *   The entity being resolved from.
 * @param \Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeFieldTypeInterface $plugin
 *   The GraphQL Compose field plugin currently being resolved.
 * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
 *   Context for the current resolution.
 */
function hook_graphql_compose_field_results_alter(array &$results, $entity, GraphQLComposeFieldTypeInterface $plugin, FieldContext $context) {

  $field_definition = $plugin->getFieldDefinition();

  $field_name = $field_definition->getName();
  $entity_type = $field_definition->getTargetEntityTypeId();

  // Replace the results.
  if ($entity_type === 'node' &&  $field_name === 'field_potato') {
    $results = ['new node value for field_potato'];
  }

  // The actual entity for the field being resolved.
  if ($entity?->id() === '123') {
    $results = ['This is node 123'];
  }
}

/**
 * Alter defined interfaces on an entity type.
 *
 * @param array $interfaces
 *   Interfaces defined on entity type.
 * @param \Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeEntityTypeInterface $plugin
 *   The current entity type being processed.
 * @param string|null $bundle_id
 *   The current bundle being processed.
 */
function hook_graphql_compose_entity_interfaces_alter(array &$interfaces, GraphQLComposeEntityTypeInterface $plugin, ?string $bundle_id) {
  if ($plugin->getEntityTypeId() === 'node') {
    $interfaces[] = 'TestNodes';
  }

  if ($plugin->getEntityType()->entityClassImplements(FieldableEntityInterface::class)) {
    $fields = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions($plugin->getEntityTypeId(), $bundle_id);

    if (isset($fields['field_tags'])) {
      $interfaces[] = 'TaggableInterface';
    }
  }
}

/**
 * Change enabled state of an entity plugin bundle.
 *
 * @param bool $enabled
 *   Field is enabled or not.
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The current entity bundle being processed.
 */
function hook_graphql_compose_entity_bundle_enabled_alter(bool &$enabled, EntityInterface $entity) {
  if ($entity->id() === 'user') {
    $enabled = FALSE;
  }
}

/**
 * Alter defined base fields available to an entity type.
 *
 * @param array $fields
 *   Fields defined on entity type.
 * @param string $entity_type_id
 *   The current entity type being processed.
 */
function hook_graphql_compose_entity_base_fields_alter(array &$fields, string $entity_type_id) {
  if ($entity_type_id === 'user') {
    unset($fields['mail']);
  }
}

/**
 * Alter the entity type form GraphQL settings.
 *
 * Note: You should hook alter the config schema if you edit this.
 * Alter config schema using hook_config_schema_info_alter().
 * See graphql_compose_routes.module for an example.
 *
 * @param array $form
 *   Drupal form array.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   Drupal form state.
 * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
 *   Current entity type.
 * @param string $bundle_id
 *   Current entity bundle id.
 * @param array $settings
 *   Current settings.
 */
function hook_graphql_compose_entity_type_form_alter(array &$form, FormStateInterface $form_state, EntityTypeInterface $entity_type, string $bundle_id, array $settings) {
  $form['my_setting'] = [
    '#default_value' => $settings['my_setting'] ?? NULL,
  ];
}

/**
 * Alter the field type form GraphQL settings.
 *
 * Note: You should hook alter the config schema if you edit this.
 * Alter config schema using hook_config_schema_info_alter().
 * See graphql_compose_views.module for an example.
 *
 * @param array $form
 *   Drupal form array.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   Drupal form state.
 * @param \Drupal\Core\Field\FieldDefinitionInterface $field
 *   Current field type.
 * @param array $settings
 *   Current settings.
 */
function hook_graphql_compose_field_type_form_alter(array &$form, FormStateInterface $form_state, FieldDefinitionInterface $field, array $settings) {
  $form['my_setting'] = [
    '#default_value' => $settings['my_setting'] ?? NULL,
  ];
}
