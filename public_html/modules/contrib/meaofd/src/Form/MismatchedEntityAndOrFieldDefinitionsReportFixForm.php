<?php

namespace Drupal\meaofd\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\meaofd\Services\Fixer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a confirmation form for fixing an entity.
 */
class MismatchedEntityAndOrFieldDefinitionsReportFixForm extends ConfirmFormBase {

  /**
   * The mismatched entity and/or field definitions fixer service.
   *
   * @var \Drupal\meaofd\Services\Fixer
   */
  protected $fixer;

  /**
   * The entity type to fix.
   *
   * @var string|null
   */
  protected $entityType;

  /**
   * Constructor.
   *
   * @param \Drupal\meaofd\Services\Fixer $fixer
   *   The mismatched entity and/or field definitions fixer service.
   */
  public function __construct(Fixer $fixer) {
    $this->fixer = $fixer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('meaofd.fixer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'meaofd_fix_confirmation_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to fix mismatched %entity_type entity and/or field definitions?', ['%entity_type' => $this->entityType]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Fix');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('meaofd.report');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type = NULL): array {
    $this->entityType = $entity_type;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (is_string($this->entityType) && strlen($this->entityType) > 0) {
      // Create the operations' batch.
      $batch = new BatchBuilder();
      $batch->setTitle($this->t('Fixing mismatched %entity_type entity and/or field definitions', ['%entity_type' => $this->entityType]))
        ->addOperation(
          get_called_class() . '::batchProcess',
          [
            $this->entityType,
          ],
        )
        ->setFinishCallback([get_called_class(), 'batchFinished']);
      // Add batch operations as new batch sets.
      batch_set($batch->toArray());
    }
    else {
      $this->messenger()->addError($this->t('Invalid entity type.'));
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * Batch processing callback.
   *
   * @param string $entity_type
   *   The entity type to fix.
   * @param array $context
   *   The batch context.
   */
  public static function batchProcess(string $entity_type, array &$context): void {
    $entities_fixed = \Drupal::service('meaofd.fixer')->fix($entity_type, TRUE, TRUE);
    $context['results'] = $context['results'] ?? [];
    $context['results'] = array_merge($context['results'], $entities_fixed);
    $context['message'] = new TranslatableMarkup('Processing...');
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   TRUE if the batch process was successful, FALSE otherwise.
   * @param array $results
   *   An array of results from the batch process.
   * @param array $operations
   *   An array of operations that were run.
   */
  public static function batchFinished(bool $success, array $results, array $operations): void {
    /** @var \Drupal\Core\Messenger\MessengerInterface $messenger */
    $messenger = \Drupal::messenger();

    if ($success) {
      foreach ($results as $entity_fixed) {
        $messenger->addMessage(new TranslatableMarkup("Mismatched %entity_type entity and/or field definitions has been fixed.", ['%entity_type' => $entity_fixed]));
      }
    }
    else {
      $messenger->addError(new TranslatableMarkup('An error occurred during the batch operation.'));
    }
  }

  /**
   * Custom access callback to check if the entity type has meaofd's.
   *
   * @param string $entity_type
   *   The entity type to check.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function accessIfEntityHasMismatchedEntityAndOrFieldDefinitions(string $entity_type): AccessResult {
    return AccessResult::allowedIf($this->fixer->entityTypeHasChanges($entity_type))
      ->addCacheTags(['entity:' . $entity_type]);
  }

}
