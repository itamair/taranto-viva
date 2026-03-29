<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQLCompose;

use Drupal\Core\Field\FieldDefinitionInterface;

use function Symfony\Component\String\u;

/**
 * Extension for field plugin to enable unions.
 */
trait FieldUnionTrait {

  /**
   * The GraphQL type for this field.
   *
   * Replace SDL with union when multiple unions are returned.
   * If single target bundle, return just that target's type_sdl.
   *
   * @return string
   *   The generic type for the entity reference.
   */
  public function getTypeSdl(): string {
    // Enable overriding.
    if (!empty($this->pluginDefinition['type_sdl'])) {
      return $this->pluginDefinition['type_sdl'];
    }

    // No field, unable to get target type.
    if (!$field_definition = $this->getFieldDefinition()) {
      return 'UnsupportedType';
    }

    // Unknown target type.
    if (!$target_type_id = $field_definition->getSetting('target_type')) {
      return 'UnsupportedType';
    }

    // Entity type not defined.
    if (!$entity_type = $this->entityTypeManager->getDefinition($target_type_id, FALSE)) {
      return 'UnsupportedType';
    }

    // Entity type plugin not defined.
    if (!$plugin_instance = $this->gqlEntityTypeManager->getPluginInstance($target_type_id)) {
      return 'UnsupportedType';
    }

    // No enabled plugin bundles.
    if (empty($plugin_instance->getBundles())) {
      return 'UnsupportedType';
    }

    // No bundle types on this entity.
    // No union required. Return normal name.
    if (!$entity_type->getBundleEntityType()) {
      return $plugin_instance->getTypeSdl();
    }

    // Return the entity type wide union.
    if ($this->isGenericUnion()) {
      return $plugin_instance->getUnionTypeSdl();
    }

    // Return unique union for this field.
    return $this->getUnionTypeSdl();
  }

  /**
   * Check if this field should be a generic union.
   *
   * @return bool
   *   True if enabled.
   */
  public function isGenericUnion(): bool {

    // If field is base field, it MUST be generic.
    if ($this->isBaseField()) {
      return TRUE;
    }

    // This is configurable in the GraphQL Compose settings.
    return \Drupal::config('graphql_compose.settings')->get('settings.simple_unions') ?: FALSE;
  }

  /**
   * Check if this field's union will return just a single type.
   *
   * @return bool
   *   True if single type.
   */
  public function isSingleUnion(): bool {
    $mapping = $this->getUnionTypeMapping();

    return (count($mapping) === 1);
  }

  /**
   * The GraphQL union type for this field (non generic).
   *
   * @return string
   *   Bundle in format of {Entity}{Bundle}{Fieldname}Union
   */
  public function getUnionTypeSdl(): string {

    // Get the field type bundle. Eg ParagraphText.
    if (!$bundle = $this->getEntityWrapper()) {
      return 'UnsupportedType';
    }

    // Ensure we have something to map.
    if (!$union_mapping = $this->getUnionTypeMapping()) {
      return 'UnsupportedType';
    }

    // If single type, return first type configured.
    if ($this->isSingleUnion()) {
      return reset($union_mapping);
    }

    // Generate a new type for the field.
    $field_type_sdl = u($this->getNameSdl())
      ->camel()
      ->title()
      ->toString();

    // Generate a new union type for the entity bundle.
    return u($bundle->getTypeSdl())
      ->append($field_type_sdl)
      ->append('Union')
      ->toString();
  }

  /**
   * Get the target types for target type unions.
   *
   * @return array
   *   Schema types available to union.
   */
  public function getUnionTypeMapping(): array {
    $mapping = &drupal_static('graphql_compose_union_type_mapping', []);

    $field_definition = $this->getFieldDefinition();
    $field_id = $field_definition->getUniqueIdentifier();

    if (isset($mapping[$field_id])) {
      return $mapping[$field_id];
    }

    $mapping[$field_id] = [];

    $target_types = $this->getUnionTargetTypes($field_definition);
    foreach ($target_types as $entity_type_id) {
      $mapping[$field_id] += $this->getUnionTargetBundles($field_definition, $entity_type_id);
    }

    return $mapping[$field_id];
  }

  /**
   * Get the target types for target type unions.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   *
   * @return string[]
   *   The target entity types.
   */
  protected function getUnionTargetTypes(FieldDefinitionInterface $field_definition): array {
    $target_types = $field_definition->getSetting('target_type');
    return is_array($target_types) ? $target_types : [$target_types];
  }

  /**
   * Get the target bundles for entity type.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param string $entity_type_id
   *   The entity type id.
   *
   * @return string[]
   *   The target entity types available to map.
   */
  protected function getUnionTargetBundles(FieldDefinitionInterface $field_definition, string $entity_type_id) {
    $result = [];

    // This entity type is not supported by graphql compose.
    $plugin_instance = $this->gqlEntityTypeManager->getPluginInstance($entity_type_id);
    if (!$plugin_instance) {
      return [];
    }

    // Get the target configuration from the field.
    $handler_settings = $field_definition->getSetting('handler_settings');
    if (!$handler_settings) {
      // Look for type specific config (eg dynamic entity reference)
      $type_settings = $field_definition->getSetting($entity_type_id);
      $handler_settings = $type_settings['handler_settings'] ?? [];
    }

    $target_bundles = $handler_settings['target_bundles'] ?? [] ?: [];
    $all_bundles = array_keys($this->entityTypeBundleInfo->getBundleInfo($entity_type_id));

    // Some plugins allow you to negate your selection.
    $negate = (bool) ($handler_settings['negate'] ?? FALSE);

    if ($negate) {
      // Get the opposite of the selected bundles.
      $target_bundles = array_diff($all_bundles, $target_bundles);
    }
    else {
      // Use "all" if nothing selected.
      $target_bundles = $target_bundles ?: $all_bundles;
    }

    // Limit mapping to enabled bundles within the entity type plugin.
    foreach ($target_bundles as $bundle_id) {
      if ($target_bundle = $plugin_instance->getBundle($bundle_id)) {
        $result[$entity_type_id . ':' . $bundle_id] = $target_bundle->getTypeSdl();
      }
    }

    return $result;
  }

}
