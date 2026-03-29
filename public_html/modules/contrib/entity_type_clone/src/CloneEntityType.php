<?php

namespace Drupal\entity_type_clone;

use Drupal\block_content\Entity\BlockContentType;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_type_clone\Controller\EntityTypeCloneController;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\profile\Entity\ProfileType;
use Drupal\storage\Entity\StorageType;
use Drupal\taxonomy\Entity\Vocabulary;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;

/**
 * Class for cloning entity type data form.
 *
 * @package Drupal\entity_type_clone\Form
 */
class CloneEntityType {

  /**
   * Clones a entity type field.
   *
   * @param array $data
   *   Contains the field to clone and $form_state data.
   * @param array $context
   *   A reference to the batch operation context.
   */
  public static function cloneEntityTypeField(array $data, array &$context) {
    // Get the source field name.
    $sourceFieldName = $data['field']->getName();
    // Clone the field.
    // Only create a duplicate of an entity if the field implements,
    // EntityInterface (as this is not guaranteed e.g. for Content moderation).
    if ($data['field'] instanceof EntityInterface) {
      // Double check if the field has not already been added by another module (e.g. Domain).
      $existing_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions($data['values']['show']['entity_type'], $data['values']['clone_bundle_machine']);
      if (!array_key_exists($sourceFieldName, $existing_fields)) {
        $targetFieldConfig = $data['field']->createDuplicate();
        $targetFieldConfig->set('entity_type', $data['values']['show']['entity_type']);
        $targetFieldConfig->set('bundle', $data['values']['clone_bundle_machine']);
        $targetFieldConfig->save();
      }
    }
    // Copy the form display.
    $form_mode_displays = \Drupal::service('entity_display.repository')
      ->getFormModeOptionsByBundle($data['values']['show']['entity_type'], $data['values']['show']['type']);
    if ($form_mode_displays) {
      foreach ($form_mode_displays as $form_mode_display => $value) {
        EntityTypeCloneController::copyFieldDisplay('form', $form_mode_display, $data);
      }
    }
    $config_factory = \Drupal::configFactory();
    $modes = $config_factory->listAll('core.entity_view_display' . '.' . $data['values']['show']['entity_type'] . '.' . $data['values']['show']['type']);
    if ($modes) {
      foreach ($modes as $mode) {
        $mode_explode = explode('.', $mode);
        $view_mode = $mode_explode[4];
        // Copy the view display.
        EntityTypeCloneController::copyFieldDisplay('view', $view_mode, $data);
      }
    }
    // Update the progress information.target_machine_name.
    if (empty($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
    }
    $context['sandbox']['progress'];
    $context['sandbox']['current_item'] = $sourceFieldName;
    $context['message'] = t(
      'Field @source successfully cloned.', ['@source' => $sourceFieldName]
    );
    $context['results']['fields'][] = $sourceFieldName;
  }

  /**
   * Clones a entity type.
   *
   * @param array $values
   *   Contains the values of the form submitted via $form_state.
   * @param array $context
   *   A reference to the batch operation context.
   */
  public static function cloneEntityTypeData(array $values, array &$context) {
    // Prepare the progress array.
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
    }
    // Load the source entity type.
    if ($values['show']['entity_type'] === 'node') {
      $sourceContentType = NodeType::load($values['show']['type']);
      if (isset($sourceContentType)) {
        // Create the target entity type.
        $targetContentType = $sourceContentType->createDuplicate();
        $targetContentType->set('uuid', \Drupal::service('uuid')->generate());
        $targetContentType->set('name', $values['clone_bundle']);
        $targetContentType->set('type', $values['clone_bundle_machine']);
        $targetContentType->set('originalId', $values['clone_bundle_machine']);
        $targetContentType->set('description', $values['target_description']);
        $targetContentType->save();
      }
    }
    if ($values['show']['entity_type'] === 'paragraph') {
      $sourceParagraphType = ParagraphsType::load($values['show']['type']);
      if (isset($sourceParagraphType)) {
        // Create the target entity type.
        $targetParagraphType = $sourceParagraphType->createDuplicate();
        $targetParagraphType->set('uuid', \Drupal::service('uuid')->generate());
        $targetParagraphType->set('label', $values['clone_bundle']);
        $targetParagraphType->set('id', $values['clone_bundle_machine']);
        $targetParagraphType->set('originalId', $values['clone_bundle_machine']);
        $targetParagraphType->set('description', $values['target_description']);
        $targetParagraphType->save();
      }
    }
    if ($values['show']['entity_type'] === 'taxonomy_term') {
      $vocabulary = Vocabulary::create([
        'vid' => $values['clone_bundle_machine'],
        'description' => $values['target_description'],
        'name' => $values['clone_bundle'],
      ]);
      $vocabulary->set('uuid', \Drupal::service('uuid')->generate());
      $vocabulary->save();
    }
    // Clone block content.
    if ($values['show']['entity_type'] === 'block_content') {
      $sourceParagraphType = BlockContentType::load($values['show']['type']);
      if (isset($sourceParagraphType)) {
        // Create the target entity type.
        $targetBlockType = $sourceParagraphType->createDuplicate();
        $targetBlockType->set('uuid', \Drupal::service('uuid')->generate());
        $targetBlockType->set('label', $values['clone_bundle']);
        $targetBlockType->set('id', $values['clone_bundle_machine']);
        $targetBlockType->set('originalId', $values['clone_bundle_machine']);
        $targetBlockType->set('description', $values['target_description']);
        $targetBlockType->save();
      }
    }
    if ($values['show']['entity_type'] === 'profile') {
      $profile_type_load = ProfileType::load($values['show']['type']);
      if (isset($profile_type_load)) {
        $type = ProfileType::create([
          'id' => $values['clone_bundle_machine'],
          'label' => $values['clone_bundle'],
          'description' => $values['target_description'] ?? $profile_type_load->getDescription(),
          'registration' => $profile_type_load->getRegistration(),
          'multiple' => $profile_type_load->allowsMultiple(),
          'roles' => $profile_type_load->getRoles(),
        ]);
        $type->save();
      }
    }
    if ($values['show']['entity_type'] === 'storage') {
      $storage_type_load = StorageType::load($values['show']['type']);
      if (isset($storage_type_load)) {
        $storageTypeSave = StorageType::create([
          'id' => $values['clone_bundle_machine'],
          'label' => $values['clone_bundle'],
          'description' => $values['target_description'] ?? $storage_type_load->getDescription(),
        ]);
        $storageTypeSave->save();
      }
    }

    // Clone extra fields for view modes.
    // @todo the same for extra fields in form modes.
    self::setInitExtraFields($values);

    // Update the progress information.
    $context['sandbox']['progress'];
    $context['sandbox']['current_item'] = $values['show']['type'];
    $context['message'] = t(
      'Entity type @source successfully cloned.', ['@source' => $values['show']['type']]
    );
    $context['results']['source'][] = $values['show']['type'];
    $context['results']['target'][] = $values['clone_bundle_machine'];
  }

  /**
   * Handles results after the batch operations.
   *
   * @param bool $success
   *   The status of the batch process.
   * @param array $results
   *   Contains the results of the batch operation.
   * @param array $operations
   *   The array of operations processed by the batch.
   */
  public static function cloneEntityTypeFinishedCallback($success, array $results, array $operations) {
    // Check batch operations success.
    if ($success) {
      $message = t('"@source" type and @fields field(s) cloned successfuly to "@target".', [
        '@source' => $results['source'][0],
        '@fields' => isset($results['fields']) ? count($results['fields']) : 0,
        '@target' => $results['target'][0],
      ]);
    }
    else {
      $message = t('Finished with an error.');
    }
    // Send the result message.
    \Drupal::messenger()->addStatus($message);
    // Redirect to the entity type clone page.
    $response = new RedirectResponse('admin/config/entity-type-clone');
    $response->send();
  }

  /**
   * Clone extra fields for view modes.
   *
   * @param array $values
   *   Contains the values of the form submitted via $form_state.
   */
  private static function setInitExtraFields(array $values): void {
    $config_factory = \Drupal::configFactory();
    $entity_display = \Drupal::service('entity_display.repository');
    $entity_field = \Drupal::service('entity_field.manager');

    // Get active view modes from source entity.
    $modes = $config_factory->listAll('core.entity_view_display' . '.' . $values['show']['entity_type'] . '.' . $values['show']['type']);
    $extra_fields = $entity_field->getExtraFields($values['show']['entity_type'], $values['show']['type']);
    if ($modes) {
      // Loop through each view mode configuration.
      foreach ($modes as $mode) {
        // Extract the view mode from the configuration name.
        $view_mode = self::extractViewMode($mode);
        // Get the source content for the specific view mode.
        $source_content = self::getSourceContent($entity_display, $values['show']['entity_type'], $values['show']['type'], $view_mode);
        // Get the target display object for the cloned entity type and view mode.
        $target_cloned_display = $entity_display->getViewDisplay($values['show']['entity_type'], $values['clone_bundle_machine'], $view_mode);
        // Clone the extra fields from the source to the target display.
        self::cloneExtraFields($target_cloned_display, $extra_fields, $source_content);
        // Save the updated target display configuration.
        $target_cloned_display->save();
      }
    }
  }

  /**
   * Extract the view mode from the configuration name.
   *
   * @param string $mode
   *   The configuration name for the view mode.
   *
   * @return string
   *   The extracted view mode.
   */
  private static function extractViewMode(string $mode): string {
    $mode_explode = explode('.', $mode);
    return $mode_explode[4];
  }

  /**
   * Get the source content for a given view mode.
   *
   * @param EntityDisplayRepositoryInterface $entity_display
   *   The entity display repository service.
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle_type
   *   The bundle type.
   * @param string $view_mode
   *
   * @return array
   *   The source content array.
   */
  private static function getSourceContent(EntityDisplayRepositoryInterface $entity_display, string $entity_type, string $bundle_type, string $view_mode): array {
    return $entity_display->getViewDisplay($entity_type, $bundle_type, $view_mode)->get('content');
  }

  /**
   * Clone the extra fields from source to target display.
   *
   * @param EntityViewDisplayInterface $target_cloned_display
   *   The target cloned display object.
   * @param array $extra_fields
   *   The extra fields array.
   * @param array $source_content
   *   The source content array.
   */
  private static function cloneExtraFields(EntityViewDisplayInterface $target_cloned_display, array $extra_fields, array $source_content): void {
    // Check each extra field.
    foreach ($extra_fields['display'] as $extra_field => $extra_field_info) {
      // Reset by deleting all extra fields.
      $target_cloned_display->removeComponent($extra_field);
      // Add to content (active) section to display.
      if (isset($source_content[$extra_field])) {
        $target_cloned_display->setComponent($extra_field, $source_content[$extra_field]);
      }
    }
  }



}
