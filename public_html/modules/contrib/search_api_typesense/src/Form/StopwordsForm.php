<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\search_api\ServerInterface;
use Drupal\search_api_typesense\Api\SearchApiTypesenseException;
use Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException;
use Drupal\search_api_typesense\Api\TypesenseClientInterface;
use Drupal\search_api_typesense\LoggerTrait;
use Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend;

/**
 * Manage the Stop Words.
 */
class StopwordsForm extends FormBase {

  use LoggerTrait;

  /**
   * The Typesense client.
   *
   * @var \Drupal\search_api_typesense\Api\TypesenseClientInterface
   */
  protected TypesenseClientInterface $typesenseClient;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'search_api_typesense_stop_words';
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
    ?ServerInterface $search_api_server = NULL,
  ): array {
    if ($search_api_server === NULL) {
      throw new SearchApiTypesenseException(
        'The search API server is not available.',
      );
    }

    $backend = $search_api_server->getBackend();
    if (!$backend instanceof SearchApiTypesenseBackend) {
      throw new SearchApiTypesenseException('The server must use the Typesense backend.');
    }

    if (!$backend->isAvailable()) {
      $this->messenger()->addError(
        $this->t('The Typesense server is not available.'),
      );

      return $form;
    }

    $this->typesenseClient = $backend->getTypesenseClient();

    try {
      $stopwords = $this->typesenseClient->retrieveStopwords();
    }
    catch (SearchApiTypesenseRequestUnauthorizedException $e) {
      $this->messenger()->addError($e->getMessage());

      return $form;
    }

    $documentation_link = Link::fromTextAndUrl(
      $this->t('documentation'),
      Url::fromUri(
        'https://typesense.org/docs/26.0/api/stopwords.html#adding-stopwords',
        [
          'attributes' => [
            'target' => '_blank',
          ],
        ],
      ),
    );

    $op = $this->getRequest()->query->get('op') ?? 'add';
    $stopword = NULL;
    if ($op === 'edit') {
      try {
        $stopword = $this->typesenseClient->retrieveStopword(
          $this->getRequest()->query->get('id'),
        );
      }
      catch (\Exception $e) {
        $this->messenger()->addError($e->getMessage());
      }

      $stopword = $stopword['stopwords'];
    }

    $form['stopwords'] = [
      '#type' => 'details',
      '#title' => $op === 'edit' ? $this->t('Edit a stopword set') : $this->t('Add a stopword set'),
      '#description' => $this->t('See the @link for more information.', [
        '@link' => $documentation_link->toString(),
      ]),
      '#open' => !(\count($stopwords['stopwords']) > 0) || $op === 'edit',
    ];

    $form['stopwords']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#description' => $this->t('The name of the stopwords set.'),
      '#size' => 30,
      '#required' => TRUE,
      '#default_value' => $op === 'edit' ? $stopword['id'] : '',
      '#disabled' => $op === 'edit',
    ];

    $form['stopwords']['stopwords'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Stopwords'),
      '#description' => $this->t('List of stopwords. Separate words with comma.'),
      '#default_value' => $op === 'edit' ? \implode(',',
        $stopword['stopwords']) : '',
      '#required' => TRUE,
    ];

    $form['stopwords']['locale'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Locale'),
      '#description' => $this->t('Locale for the stopwords.'),
      '#default_value' => $op === 'edit' ? $stopword['locale'] : '',
      '#required' => TRUE,
    ];

    $form['server_id'] = [
      '#type' => 'value',
      '#value' => $search_api_server->id(),
    ];

    $form['stopwords']['operations'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $op === 'edit' ? $this->t('Update') : $this->t('Add new'),
      ],
    ];

    $form['existing_keys']['list'] = $this
      ->buildExistingStopwordsTable($stopwords, $search_api_server->id());

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
    try {
      $response = $this->typesenseClient->createStopword([
        'name' => $form_state->getValue('name'),
        'stopwords' => \explode(',', $form_state->getValue('stopwords')),
        'locale' => $form_state->getValue('locale'),
      ]);

      $op = $this->getRequest()->query->get('op') ?? 'add';
      if ($op === 'edit') {
        $this->messenger()->addStatus(
          $this->t('Stopword %id has been updated.', [
            '%id' => $response['id'],
          ]),
        );
      }
      else {
        $this->messenger()->addStatus(
          $this->t('Stopword %id has been added.', [
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

    $form_state->setRedirect('search_api_typesense.server.stopwords', [
      'search_api_server' => $form_state->getValue('server_id'),
    ]);
  }

  /**
   * Builds the existing stopwords table.
   *
   * @param array $stopwords
   *   The existing stopwords.
   * @param string $server_id
   *   The server ID.
   *
   * @return array
   *   The existing keys table.
   */
  protected function buildExistingStopwordsTable(
    array $stopwords,
    string $server_id,
  ): array {
    $table = [
      '#type' => 'table',
      '#caption' => $this->t('Existing stopwords'),
      '#header' => [
        $this->t('Name'),
        $this->t('Stopwords'),
        $this->t('Locale'),
        $this->t('Operations'),
      ],
      '#empty' => $this->t('No stopwords found.'),
    ];

    $rows = [];
    foreach ($stopwords['stopwords'] as $key => $value) {
      $rows[$key] = [
        'name' => $value['id'],
        'stopwords' => \implode(', ', $value['stopwords']),
        'locale' => $value['locale'],
        'operations' => [
          'data' => [
            '#type' => 'dropbutton',
            '#dropbutton_type' => 'small',
            '#links' => [
              'edit' => [
                'title' => $this
                  ->t('Edit'),
                'url' => Url::fromRoute(
                  'search_api_typesense.server.stopwords', [
                    'search_api_server' => $server_id,
                    'op' => 'edit',
                    'id' => $value['id'],

                  ],
                ),
              ],
              'delete' => [
                'title' => $this
                  ->t('Delete'),
                'url' => Url::fromRoute(
                  'search_api_typesense.server.stopwords.delete', [
                    'search_api_server' => $server_id,
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
