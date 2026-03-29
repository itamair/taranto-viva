<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\search_api\IndexInterface;
use Drupal\search_api_typesense\Api\SearchApiTypesenseException;
use Drupal\search_api_typesense\Api\TypesenseClientInterface;
use Drupal\search_api_typesense\LoggerTrait;
use Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend;

/**
 * Form to delete a synonym.
 */
class SynonymDeleteForm extends ConfirmFormBase {

  use BackendTrait;
  use LoggerTrait;

  /**
   * The Typesense client.
   *
   * @var \Drupal\search_api_typesense\Api\TypesenseClientInterface
   */
  protected TypesenseClientInterface $typesenseClient;

  /**
   * The search API index.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  private IndexInterface $searchApiIndex;

  /**
   * The synonym ID.
   *
   * @var string|null
   */
  private ?string $synonymId;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'search_api_typesense_synonym_delete';
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<array-key, mixed> $form
   * @phpstan-return array<array-key, mixed>
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    ?IndexInterface $search_api_index = NULL,
    ?string $id = NULL,
  ): array {
    if ($search_api_index === NULL) {
      throw new SearchApiTypesenseException(
        'The search API index is not available.',
      );
    }

    $backend = $search_api_index->getServerInstance()?->getBackend();
    if (!$backend instanceof SearchApiTypesenseBackend) {
      throw new SearchApiTypesenseException('The server must use the Typesense backend.');
    }

    if (!$backend->isAvailable()) {
      $this->messenger()->addError(
        $this->t('The Typesense server is not available.'),
      );
    }

    $this->searchApiIndex = $search_api_index;
    $this->synonymId = $id;
    $this->typesenseClient = $backend->getTypesenseClient();

    return parent::buildForm($form, $form_state);
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
    try {
      $backend = $this->getBackend($this->searchApiIndex);
      $collection_name = $backend->getCollectionName($this->searchApiIndex);
      $synonym = $this->typesenseClient->deleteSynonym($collection_name, $this->synonymId);

      $this->messenger()->addStatus($this->t('Synonym %id has been deleted.', [
        '%id' => $synonym['id'],
      ]));
    }
    catch (SearchApiTypesenseException $e) {
      $this->logError(
        $e,
        $this->t('The synonym could not be deleted'),
      );
    }

    $form_state->setRedirect('search_api_typesense.collection.synonyms', [
      'search_api_index' => $this->searchApiIndex->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   */
  public function getQuestion(): TranslatableMarkup {
    $backend = $this->getBackend($this->searchApiIndex);
    $collection_name = $backend->getCollectionName($this->searchApiIndex);
    $synonym = $this->typesenseClient->retrieveSynonym($collection_name, $this->synonymId);

    return $this->t('Do you want to delete synonym %id?', [
      '%id' => $synonym['id'],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('search_api_typesense.collection.synonyms', [
      'search_api_index' => $this->searchApiIndex->id(),
    ]);
  }

}
