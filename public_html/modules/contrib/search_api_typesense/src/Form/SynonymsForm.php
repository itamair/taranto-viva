<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api_typesense\Api\SearchApiTypesenseException;
use Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException;
use Drupal\search_api_typesense\Api\TypesenseClientInterface;
use Drupal\search_api_typesense\LoggerTrait;
use Drupal\search_api_typesense\TypesenseTrait;

/**
 * Manage the synonyms.
 */
final class SynonymsForm extends FormBase {

  use BackendTrait;
  use LoggerTrait;
  use TypesenseTrait;

  /**
   * The Typesense client.
   *
   * @var \Drupal\search_api_typesense\Api\TypesenseClientInterface
   */
  protected TypesenseClientInterface $typesenseClient;

  /**
   * The name of the collection that the synonyms belong to.
   *
   * @var string
   */
  protected string $collectionName;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'search_api_typesense_synonyms';
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<array-key, mixed> $form
   * @phpstan-return array<array-key, mixed>
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
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
      $backend = $this->getBackend($search_api_index);
    }
    catch (SearchApiException $e) {
      $this->messenger()->addError($e->getMessage());

      return [];
    }

    $form['#title'] = $this->t('Manage synonyms for search index %label',
      ['%label' => $search_api_index->label()]);

    $this->typesenseClient = $backend->getTypesenseClient();
    $this->collectionName = $backend->getCollectionName($search_api_index);

    try {
      $synonyms = $this->typesenseClient->retrieveSynonyms($this->collectionName);
    }
    catch (SearchApiTypesenseRequestUnauthorizedException $e) {
      $this->messenger()->addError($e->getMessage());

      return $form;
    }

    $documentation_link = Link::fromTextAndUrl(
      $this->t('documentation'),
      Url::fromUri(
        'https://typesense.org/docs/latest/api/synonyms.html',
        [
          'attributes' => [
            'target' => '_blank',
          ],
        ],
      ),
    );

    $op = $this->getRequest()->query->get('op') ?? 'add';
    $synonym = NULL;
    if ($op === 'edit') {
      try {
        $synonym = $this->typesenseClient->retrieveSynonym(
          $this->collectionName,
          $this->getRequest()->query->get('id'),
        );
      }
      catch (SearchApiTypesenseException $e) {
        $this->messenger()->addError($e->getMessage());

        return [];
      }
    }

    $form['synonym'] = [
      '#type' => 'details',
      '#title' => $op === 'edit' ? $this->t('Edit synonym') : $this->t('Add synonym'),
      '#description' => $this->t('See the @link for more information.', [
        '@link' => $documentation_link->toString(),
      ]),
      '#open' => !(\count($synonyms['synonyms']) > 0) || $op === 'edit',
    ];

    $form['synonym']['id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Id'),
      '#description' => $this->t('Unique identifier for the synonym. It cannot contain the / character.'),
      '#required' => TRUE,
      '#default_value' => $op === 'edit' ? $this->getRequest()->query->get('id') : $this->generateUuid(),
      '#disabled' => $op === 'edit',
    ];

    $default_type = 'one_way';
    if ($op === 'edit') {
      if ($synonym['root'] === '') {
        $default_type = 'multi_way';
      }
    }
    $form['synonym']['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#options' => [
        'one_way' => $this->t('One-way'),
        'multi_way' => $this->t('Multi-way'),
      ],
      '#default_value' => $op === 'edit' ? $default_type : 'one_way',
    ];

    $form['synonym']['root'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Root'),
      '#description' => $this->t('For 1-way synonyms, indicates the root word that words in the synonyms parameter map to.'),
      '#default_value' => $op === 'edit' ? $synonym['root'] : '',
      '#states' => [
        'visible' => [
          ':input[name="type"]' => ['value' => 'one_way'],
        ],
        'required' => [
          ':input[name="type"]' => ['value' => 'one_way'],
        ],
      ],
    ];

    $form['synonym']['synonyms'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Synonyms'),
      '#description' => $this->t('List of words that should be considered as synonyms. Separate words with comma.'),
      '#default_value' => $op === 'edit' ? \implode(',',
        $synonym['synonyms']) : '',
      '#required' => TRUE,
    ];

    $form['synonym']['symbols_to_index'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Symbols to index'),
      '#description' => $this->t('By default, special characters are dropped from synonyms. Use this attribute to specify which special characters should be indexed as is.'),
      '#default_value' => $op === 'edit' && \array_key_exists('symbols_to_index',
        $synonym) ? \implode(',', $synonym['symbols_to_index']) : '',
    ];

    $form['synonym']['locale'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Locale'),
      '#description' => $this->t('Locale for the synonym, leave blank to use the standard tokenizer.'),
      '#default_value' => $op === 'edit' && \array_key_exists('locale',
        $synonym) ? $synonym['locale'] : '',
    ];

    $form['index_id'] = [
      '#type' => 'value',
      '#value' => $search_api_index->id(),
    ];

    $form['synonym']['operations'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $op === 'edit' ? $this->t('Update') : $this->t('Add new'),
      ],
    ];

    if ($op === 'edit') {
      $form['synonym']['operations']['cancel'] = [
        '#type' => 'link',
        '#title' => $this->t('Cancel'),
        '#url' => Url::fromRoute(
          'search_api_typesense.collection.synonyms', [
            'search_api_index' => $search_api_index->id(),
          ],
        ),
        '#attributes' => [
          'class' => ['button'],
        ],
      ];
    }

    $form['existing_synonyms']['list'] = $this->buildExistingSynonymsTable($synonyms,
      $search_api_index->id());

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
    if (!$this->checkValidId($form_state->getValue('id'))) {
      $form_state->setErrorByName('id',
        $this->t('The id cannot contain the / character.')->render());
    }
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
    $synonym = [];

    if ($form_state->getValue('type') === 'multi_way') {
      $synonym['synonyms'] = \explode(',', $form_state->getValue('synonyms'));
    }
    else {
      $synonym['root'] = $form_state->getValue('root');
      $synonym['synonyms'] = \explode(',', $form_state->getValue('synonyms'));
    }

    if ($form_state->getValue('symbols_to_index') !== '') {
      $synonym['symbols_to_index'] = \explode(',',
        $form_state->getValue('symbols_to_index'));
    }

    if ($form_state->getValue('locale') !== '') {
      $synonym['locale'] = $form_state->getValue('locale');
    }

    try {
      $response = $this->typesenseClient->createSynonym(
        $this->collectionName,
        $form_state->getValue('id'),
        $synonym,
      );

      $op = $this->getRequest()->query->get('op') ?? 'add';
      if ($op === 'edit') {
        $this->messenger()->addStatus(
          $this->t('Synonym %id has been updated.', [
            '%id' => $response['id'],
          ]),
        );
      }
      else {
        $this->messenger()->addStatus(
          $this->t('Synonym %id has been added.', [
            '%id' => $response['id'],
          ]),
        );
      }
    }
    catch (\Exception $e) {
      $this->logError(
        $e,
        $this->t('Something went wrong'),
      );
    }

    $form_state->setRedirect('search_api_typesense.collection.synonyms', [
      'search_api_index' => $form_state->getValue('index_id'),
    ]);
  }

  /**
   * Builds the existing synonyms table.
   *
   * @param array $synonyms
   *   The existing synonyms.
   * @param string $index_id
   *   The index ID.
   *
   * @return array
   *   The existing synonyms table.
   */
  protected function buildExistingSynonymsTable(
    array $synonyms,
    string $index_id,
  ): array {
    $table = [
      '#type' => 'table',
      '#caption' => $this->t('Existing synonyms'),
      '#header' => [
        $this->t('ID'),
        $this->t('Type'),
        $this->t('Root'),
        $this->t('Synonyms'),
        $this->t('Symbols to index'),
        $this->t('Locale'),
        $this->t('Operations'),
      ],
      '#empty' => $this->t('No synonyms found.'),
    ];

    $rows = [];
    foreach ($synonyms['synonyms'] as $key => $value) {
      $rows[$key] = [
        'id' => $value['id'],
        'type' => $value['root'] === '' ? $this->t('Multi-way') : $this->t('One-way'),
        'root' => $value['root'],
        'synonyms' => \implode(', ', $value['synonyms']),
        'symbols_to_index' => \array_key_exists('symbols_to_index',
          $value) ? \implode(', ', $value['symbols_to_index']) : '',
        'locale' => \array_key_exists('locale', $value) ? $value['locale'] : '',
        'operations' => [
          'data' => [
            '#type' => 'dropbutton',
            '#dropbutton_type' => 'small',
            '#links' => [
              'edit' => [
                'title' => $this
                  ->t('Edit'),
                'url' => Url::fromRoute(
                  'search_api_typesense.collection.synonyms', [
                    'search_api_index' => $index_id,
                    'op' => 'edit',
                    'id' => $value['id'],

                  ],
                ),
              ],
              'delete' => [
                'title' => $this
                  ->t('Delete'),
                'url' => Url::fromRoute(
                  'search_api_typesense.collection.synonyms.delete', [
                    'search_api_index' => $index_id,
                    'id' => $value['id'],
                  ],
                ),
              ],
            ],
          ],
        ],
      ];
    }
    $table['#rows'] = $rows;

    return $table;
  }

}
