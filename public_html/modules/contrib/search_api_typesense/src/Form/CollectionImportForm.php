<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api_typesense\Api\SearchApiTypesenseException;
use Drupal\search_api_typesense\LoggerTrait;
use Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend;

/**
 * Provides a form for importing a Typesense collection.
 */
class CollectionImportForm extends FormBase {

  use LoggerTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'search_api_typesense_collection_import';
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<array-key, mixed> $form
   * @phpstan-return array<array-key, mixed>
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    ?IndexInterface $search_api_index = NULL,
  ): array {
    if ($search_api_index === NULL) {
      throw new SearchApiTypesenseException(
        'The search API index is not available.',
      );
    }

    try {
      $search_api_server = $search_api_index->getServerInstance();
      $backend = $search_api_server?->getBackend();
      if (!$backend instanceof SearchApiTypesenseBackend) {
        throw new SearchApiTypesenseException('The server must use the Typesense backend.');
      }

      if (!$backend->isAvailable()) {
        $this->messenger()->addError(
          $this->t('The Typesense server is not available.'),
        );

        return $form;
      }

      $form['#title'] = $this->t('Import index %label',
        ['%label' => $search_api_index->label()]);

      $form['import_json'] = [
        '#type' => 'file',
        '#title' => $this->t('Collection json file'),
        '#description' => $this->t('Allowed types: @extensions.',
          ['@extensions' => 'json']),
      ];

      $form['search_api_index'] = [
        '#type' => 'hidden',
        '#value' => $search_api_index->id(),
      ];

      $form['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Upload'),
      ];
    }
    catch (SearchApiTypesenseException | SearchApiException $e) {
      $form['error'] = [
        '#markup' => $this->t('An error occurred while exporting the index: @message',
          [
            '@message' => $e->getMessage(),
          ]),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<array-key, mixed> $form
   */
  public function validateForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    $all_files = $this->getRequest()->files->get('files', []);
    if ($all_files['import_json'] !== NULL) {
      $file_upload = $all_files['import_json'];
      if ($file_upload->isValid()) {
        $form_state->setValue('import_json', $file_upload->getRealPath());

        return;
      }
    }

    $form_state->setErrorByName('import_json',
      $this->t('The file could not be uploaded.')->render());
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<array-key, mixed> $form
   */
  public function submitForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    $path = $form_state->getValue('import_json');
    if ($path !== NULL) {
      $search_api_index_id = $form_state->getValue('search_api_index');

      /** @var \Drupal\search_api\Entity\Index|null $search_api_index */
      $search_api_index = Index::load($search_api_index_id);

      if ($search_api_index === NULL) {
        $this->messenger()->addError(
          $this->t('The search API index is not available.'),
        );

        return;
      }

      $search_api_server = $search_api_index->getServerInstance();
      $backend = $search_api_server?->getBackend();

      if ($backend instanceof SearchApiTypesenseBackend) {
        $data = \file_get_contents($path);

        try {
          $collection_name = $backend->getCollectionName($search_api_index);
          $backend->getTypesenseClient()
            ->importCollectionData($collection_name,
              \json_decode($data, TRUE));

          $this->messenger()
            ->addStatus($this->t('Collection imported successfully.'));
        }
        catch (SearchApiTypesenseException $e) {
          $this->logError(
            $e,
            $this->t('Could not import the collection'),
          );
        }
      }
    }
  }

}
