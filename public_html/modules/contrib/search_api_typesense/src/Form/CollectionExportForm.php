<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api_typesense\Api\SearchApiTypesenseException;
use Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend;

/**
 * Provides a form for exporting a Typesense collection.
 */
class CollectionExportForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'search_api_typesense_collection_export';
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
      $this->messenger()->addError(
        $this->t('The search API index is not available.'),
      );

      return [];
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

      $form['#title'] = $this->t('Export index %label',
        ['%label' => $search_api_index->label()]);

      $form['search_api_index'] = [
        '#type' => 'hidden',
        '#value' => $search_api_index->id(),
      ];

      $form['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Export'),
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
  public function submitForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    $form_state->setRedirect(
      'search_api_typesense.collection.export_download',
      ['search_api_index' => $form_state->getValue('search_api_index')],
    );
  }

}
