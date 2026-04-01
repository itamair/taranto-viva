<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Form;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\search_api\ServerInterface;
use Drupal\search_api_typesense\AiModels;
use Drupal\search_api_typesense\Api\SearchApiTypesenseException;
use Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException;
use Drupal\search_api_typesense\Api\TypesenseClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Manage the Curations.
 */
class ConversationsForm extends FormBase {

  use BackendTrait;
  use DependencySerializationTrait;

  final public function __construct(
    private readonly AiModels $aiModels,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
  ): ConversationsForm {
    return new static(
      $container->get('search_api_typesense.ai_models'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'search_api_typesense_conversations';
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
    ?ServerInterface $search_api_server = NULL,
  ): array {
    if ($search_api_server === NULL) {
      throw new SearchApiTypesenseException(
        'The search API server is not available.',
      );
    }

    if (!$this->aiModels->isAiSupportAvailable()) {
      $this->messenger()->addError(
        $this->t(
          'No AI support available. Be sure to enable the <a href=":ai_module" target="_blank">AI module</a> and at least one supported AI provider.',
          [
            ':ai_module' => 'https://www.drupal.org/project/ai',
          ],
        ),
      );

      return $form;
    }

    // Reload the backed from the server to avoid serialization issues.
    $backend = $this->getBackendFromServerId($search_api_server->id());

    if (!$backend->isAvailable()) {
      $this->messenger()->addError(
        $this->t('The Typesense server is not available.'),
      );

      return $form;
    }

    $typesenseClient = $backend->getTypesenseClient();

    try {
      $conversations = $typesenseClient->retrieveConversationModels();
    }
    catch (SearchApiTypesenseRequestUnauthorizedException $e) {
      $this->messenger()->addError($e->getMessage());

      return $form;
    }

    $documentation_link = Link::fromTextAndUrl(
      $this->t('documentation'),
      Url::fromUri(
        'https://typesense.org/docs/latest/api/conversational-search-rag.html',
        [
          'attributes' => [
            'target' => '_blank',
          ],
        ],
      ),
    );

    $op = $this->getRequest()->query->get('op') ?? 'add';
    $conversation = NULL;
    if ($op === 'edit') {
      try {
        $conversation = $typesenseClient->retrieveConversationModel(
          $this->getRequest()->query->get('id'),
        );
      }
      catch (SearchApiTypesenseException $e) {
        $this->messenger()->addError($e->getMessage());

        return [];
      }
    }

    if (\count(
        $conversations,
      ) > 0 && $typesenseClient->hasConversationHistoryCollection() === FALSE) {
      $this->messenger()->addError(
        $this->t(
          'The conversation history collection does not exist. Create or update a conversation model to create the collection.',
        ),
      );
    }

    $form['conversation'] = [
      '#type' => 'details',
      '#title' => $op === 'edit' ? $this->t(
        'Edit a conversation model',
      ) : $this->t('Add a conversation model'),
      '#description' => $this->t('See the @link for more information.', [
        '@link' => $documentation_link->toString(),
      ]),
      '#open' => !(\count($conversations) > 0) || $op === 'edit',
    ];

    $form['conversation']['id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Id'),
      '#description' => $this->t('Conversation ID.'),
      '#size' => 30,
      '#required' => TRUE,
      '#default_value' => $op === 'edit' ? $conversation['id'] : '',
      '#disabled' => $op === 'edit',
    ];

    $form['conversation']['model'] = [
      '#type' => 'select',
      '#title' => $this->t('Model'),
      '#description' => $this->t('Name of the LLM to use.'),
      '#options' => $this->aiModels->getConversationModels(),
      '#default_value' => $op === 'edit' ? $conversation['model_name'] : NULL,
      '#required' => TRUE,
    ];

    $form['conversation']['system_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('System prompt'),
      '#description' => $this->t(
        'The system prompt that contains special instructions to the LLM.',
      ),
      '#required' => TRUE,
      '#default_value' => $op === 'edit' ? $conversation['system_prompt'] : '',
    ];

    $form['conversation']['max_bytes'] = [
      '#type' => 'number',
      '#title' => $this->t('Max bytes'),
      '#description' => $this->t(
        'The maximum number of bytes to send to the LLM in every API call. Consult the documentation about the LLM on the number of bytes supported in the context window.',
      ),
      '#size' => 30,
      '#required' => TRUE,
      '#default_value' => $op === 'edit' ? $conversation['max_bytes'] : '',
    ];

    $form['conversation']['ttl'] = [
      '#type' => 'number',
      '#title' => $this->t('TTL'),
      '#description' => $this->t(
        'Time interval in seconds after which the messages would be deleted.',
      ),
      '#size' => 30,
      '#required' => TRUE,
      '#default_value' => $op === 'edit' ? $conversation['ttl'] : '86400',
    ];

    $form['server_id'] = [
      '#type' => 'value',
      '#value' => $search_api_server->id(),
    ];

    $form['conversation']['operations'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $op === 'edit' ? $this->t('Update') : $this->t('Add new'),
      ],
    ];

    $form['existing_conversations']['list'] = $this
      ->buildExistingConversationModelsTable(
        $conversations,
        $search_api_server->id(),
      );

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
      $backend = $this->getBackendFromServerId(
        $form_state->getValue('server_id'),
      );
      $typesense_client = $backend->getTypesenseClient();
      $typesense_client->ensureConversationHistoryCollection();

      $op = $this->getRequest()->query->get('op') ?? 'add';
      $params = [
        'id' => $form_state->getValue('id'),
        'model_name' => $form_state->getValue('model'),
        'history_collection' => TypesenseClient::CONVERSATION_HISTORY_COLLECTION_NAME,
        'system_prompt' => $form_state->getValue('system_prompt'),
        'max_bytes' => \intval($form_state->getValue('max_bytes')),
        'ttl' => \intval($form_state->getValue('ttl')),
      ] + $this->aiModels->getConversationModelConfig(
        $form_state->getValue('model'),
      );

      if ($op === 'edit') {
        $response = $typesense_client->updateConversationModel(
          $form_state->getValue('id'),
          $params,
        );

        $this->messenger()->addStatus(
          $this->t('Conversation model %id has been updated.', [
            '%id' => $response['id'],
          ]),
        );
      }
      else {
        $response = $typesense_client->createConversationModel(
          $params,
        );

        $this->messenger()->addStatus(
          $this->t('Conversation model %id has been added.', [
            '%id' => $response['id'],
          ]),
        );
      }
    }
    catch (\Exception $e) {
      $this->logger('search_api_typesense')->error($e->getMessage());
      $this->messenger()->addError(
        $this->t('Something went wrong.'),
      );
    }

    $form_state->setRedirect('search_api_typesense.server.conversations', [
      'search_api_server' => $form_state->getValue('server_id'),
    ]);
  }

  /**
   * Builds the existing conversation models table.
   *
   * @param array $conversations
   *   The existing conversation models.
   * @param string $server_id
   *   The server ID.
   *
   * @return array
   *   The existing keys table.
   */
  protected function buildExistingConversationModelsTable(
    array $conversations,
    string $server_id,
  ): array {
    $table = [
      '#type' => 'table',
      '#caption' => $this->t('Existing conversation models'),
      '#header' => [
        $this->t('Id'),
        $this->t('Model name'),
        $this->t('API Key'),
        $this->t('System Prompt'),
        $this->t('Max Bytes'),
        $this->t('TTL'),
        $this->t('Operations'),
      ],
      '#empty' => $this->t('No conversation models found.'),
    ];

    $rows = [];
    foreach ($conversations as $key => $value) {
      $rows[$key] = [
        'name' => $value['id'],
        'model_name' => $value['model_name'],
        'api_key' => $value['api_key'],
        'system_prompt' => $value['system_prompt'],
        'max_bytes' => $value['max_bytes'],
        'ttl' => $value['ttl'],
        'operations' => [
          'data' => [
            '#type' => 'dropbutton',
            '#dropbutton_type' => 'small',
            '#links' => [
              'edit' => [
                'title' => $this
                  ->t('Edit'),
                'url' => Url::fromRoute(
                  'search_api_typesense.server.conversations',
                  [
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
                  'search_api_typesense.server.conversations.delete',
                  [
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
