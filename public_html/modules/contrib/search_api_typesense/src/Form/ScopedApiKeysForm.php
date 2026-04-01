<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\search_api\ServerInterface;
use Drupal\search_api_typesense\Api\SearchApiTypesenseException;
use Drupal\search_api_typesense\Api\TypesenseClientInterface;
use Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend;

/**
 * Generate a scoped API key.
 */
class ScopedApiKeysForm extends FormBase {

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
    return 'search_api_typesense_scoped_api_keys';
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
    $backend = $search_api_server?->getBackend();
    if (!$backend instanceof SearchApiTypesenseBackend) {
      throw new SearchApiTypesenseException(
        'The server must use the Typesense backend.',
      );
    }

    if (!$backend->isAvailable()) {
      $this->messenger()->addError(
        $this->t('The Typesense server is not available.'),
      );

      return $form;
    }

    $this->typesenseClient = $backend->getTypesenseClient();
    $documentation_link = Link::fromTextAndUrl(
      $this->t('documentation'),
      Url::fromUri(
        'https://typesense.org/docs/latest/api/api-keys.html#generate-scoped-search-key',
        [
          'attributes' => [
            'target' => '_blank',
          ],
        ],
      ),
    );

    $form['documentation'] = [
      '#markup' => $this->t(
        'For more information, see the Typesense API keys @link.',
        [
          '@link' => $documentation_link->toString(),
        ],
      ),
    ];

    $form['key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Key'),
      '#description' => $this->t('Search key.'),
      '#required' => TRUE,
    ];

    $form['parameters'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Parameters'),
      '#description' => $this->t('A list of search parameters, encoded in a JSON format. Example: {"filter_by": "company_id:124", "exclude_fields": ["password"]}'),
      '#size' => 30,
      '#required' => TRUE,
    ];

    $form['expires'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Expires at'),
      '#description' => $this->t('The expiration date of the key.'),
      '#required' => FALSE,
    ];

    $form['generate'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<array-key, mixed> $form
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   */
  public function submitForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    $key = $form_state->getValue('key');
    $parameters = $form_state->getValue('parameters');
    $parameters = \json_decode($parameters, TRUE);

    $expires = $form_state->getValue('expires');
    if ($expires !== NULL) {
      $expires = $expires->getTimestamp();

      $parameters['expires'] = $expires;
    }

    try {
      $result = $this
        ->typesenseClient
        ->generateScopedSearchKey(
          $key,
          $parameters,
        );

      $this->messenger()->addStatus(
        $this->t('The scoped key value is: %value.', [
          '%value' => $result,
        ]),
      );
      $this
        ->messenger()
        ->addWarning(
          $this->t(
            'The generated key is only returned once. Copy it somewhere safe.',
          ),
        );
    }
    catch (SearchApiTypesenseException $e) {
      $this->messenger()->addError(
        $this->t('Error generating scoped key: %error', [
          '%error' => $e->getMessage(),
        ]),
      );
    }
  }

}
