<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\search_api\ServerInterface;
use Drupal\search_api_typesense\Api\SearchApiTypesenseException;
use Drupal\search_api_typesense\Api\TypesenseClientInterface;
use Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend;

/**
 * Form to delete a conversation.
 */
class ConversationDeleteForm extends ConfirmFormBase {

  /**
   * The Typesense client.
   *
   * @var \Drupal\search_api_typesense\Api\TypesenseClientInterface
   */
  protected TypesenseClientInterface $typesenseClient;

  /**
   * The search API server.
   *
   * @var \Drupal\search_api\ServerInterface
   */
  private ServerInterface $searchApiServer;

  /**
   * The conversation model ID.
   *
   * @var string|null
   */
  private ?string $conversationModelId;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'search_api_typesense_conversation_delete';
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
    ?ServerInterface $search_api_server = NULL,
    ?string $id = NULL,
  ): array {
    if ($search_api_server === NULL) {
      throw new SearchApiTypesenseException(
        'The search API server is not available.',
      );
    }

    $backend = $search_api_server->getBackend();
    if (!$backend instanceof SearchApiTypesenseBackend) {
      throw new \InvalidArgumentException('The server must use the Typesense backend.');
    }

    if (!$backend->isAvailable()) {
      $this->messenger()->addError(
        $this->t('The Typesense server is not available.'),
      );
    }

    $this->searchApiServer = $search_api_server;
    $this->conversationModelId = $id;
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
      $conversation_model = $this->typesenseClient->deleteConversationModel($this->conversationModelId);

      $this->messenger()->addStatus($this->t('Conversation model %id has been deleted.', [
        '%id' => $conversation_model['id'],
      ]));
    }
    catch (SearchApiTypesenseException $e) {
      $this->messenger()
        ->addError($this->t('The Conversation model could not be deleted.'));
    }

    $form_state->setRedirect('search_api_typesense.server.conversations', [
      'search_api_server' => $this->searchApiServer->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    $conversation_model = $this->typesenseClient->retrieveConversationModel($this->conversationModelId);

    return $this->t('Do you want to delete conversation model %id?', [
      '%id' => $conversation_model['id'],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('search_api_typesense.server.conversations', [
      'search_api_server' => $this->searchApiServer->id(),
    ]);
  }

}
