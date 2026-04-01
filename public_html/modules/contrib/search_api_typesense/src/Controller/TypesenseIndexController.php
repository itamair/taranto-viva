<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Controller;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\search_api\IndexInterface;
use Drupal\search_api_typesense\AiModels;
use Drupal\search_api_typesense\Api\SearchApiTypesenseException;
use Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException;
use Drupal\search_api_typesense\Form\SearchOnlyKeyConfigForm;
use Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend;
use Drupal\search_api_typesense\Render\TypesenseSearchRenderContext;
use Drupal\search_api_typesense\Render\TypesenseSearchRenderServiceInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Controller for Typesense index operations.
 */
class TypesenseIndexController extends ControllerBase {

  /**
   * TypesenseIndexController constructor.
   *
   * @param \Drupal\search_api_typesense\AiModels $aiModels
   *   The AI models.
   * @param \Drupal\search_api_typesense\Render\TypesenseSearchRenderServiceInterface $renderService
   *   The render service.
   * @param \Drupal\Component\Uuid\UuidInterface $uuidService
   *   The UUID service.
   */
  public function __construct(
    #[Autowire(service: 'search_api_typesense.ai_models')]
    private readonly AiModels $aiModels,
    #[Autowire(service: 'search_api_typesense.render_service')]
    private readonly TypesenseSearchRenderServiceInterface $renderService,
    #[Autowire(service: 'uuid')]
    private readonly UuidInterface $uuidService,
  ) {
  }

  /**
   * Try the search.
   *
   * @param \Drupal\search_api\IndexInterface $search_api_index
   *   The search index.
   *
   * @return array<array-key, mixed>
   *   The render array.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function search(IndexInterface $search_api_index): array {
    try {
      $server = $search_api_index->getServerInstance();
      if ($server === NULL) {
        throw new SearchApiTypesenseException('No server instance available for the index.');
      }
      $backend = $this->renderService->validateBackend($server);
    }
    catch (SearchApiTypesenseException $e) {
      $this->messenger()->addError($e->getMessage());
      return [];
    }

    if (!$backend->isAvailable()) {
      $this->messenger()->addError(
        $this->t('The Typesense server is not available.'),
      );

      return [];
    }

    // Show the search key configuration form.
    // @phpstan-ignore-next-line
    $config_form = $this->formBuilder()->getForm(SearchOnlyKeyConfigForm::class, $server);
    $api_key = $this->resolveApiKey($backend);
    if ($api_key === NULL) {
      $this->messenger()->addError(
        $this->t('No search-only API key configured. Please set one using the form below.'),
      );
      return ['config_form' => $config_form];
    }

    $context = new TypesenseSearchRenderContext(
      backend: $backend,
      indexes: [$search_api_index],
      apiKey: $api_key,
      blockUuid: $this->uuidService->generate(),
      hitsPerPage: 8,
      debug: TRUE,
    );

    try {
      $search_render = $this->renderService->buildSearchRender($context);

      // Combine the configuration form with the search interface.
      return [
        'config_form' => $config_form,
        'search_interface' => $search_render,
      ];
    }
    catch (SearchApiTypesenseException $e) {
      $this->getLogger('search_api_typesense')->error($e->getMessage());
      $this->messenger()->addError(
        $this->t('An error occurred while rendering the search interface. For more details, check the logs.'),
      );

      return ['config_form' => $config_form];
    }
  }

  /**
   * Render the Converse page.
   *
   * @param \Drupal\search_api\IndexInterface $search_api_index
   *   The search index.
   *
   * @return array<array-key, mixed>
   *   The render array.
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   */
  public function converse(IndexInterface $search_api_index): array {
    if (!$this->aiModels->isAiSupportAvailable()) {
      $this->messenger()->addError(
        $this->t(
          'No AI support available. Be sure to enable the <a href=":ai_module" target="_blank">AI module</a> and at least one supported AI provider.',
          [
            ':ai_module' => 'https://www.drupal.org/project/ai',
          ],
        ),
      );

      return [];
    }

    $backend = $search_api_index->getServerInstance()?->getBackend();
    if (!$backend instanceof SearchApiTypesenseBackend) {
      throw new \InvalidArgumentException('The server must use the Typesense backend.');
    }

    if (!$backend->isAvailable()) {
      $this->messenger()->addError(
        $this->t('The Typesense server is not available.'),
      );

      return [];
    }

    $configuration = $backend->getConfiguration();
    $typesense_client = $backend->getTypesenseClient();
    $collection_name = $backend->getCollectionName($search_api_index);

    try {
      $conversion_models = $typesense_client->retrieveConversationModels();
    }
    catch (SearchApiTypesenseRequestUnauthorizedException $e) {
      $this->messenger()->addError($e->getMessage());

      return [];
    }

    $build = [
      '#theme' => 'search_api_typesense_converse',
      '#models' => $conversion_models,
      '#attached' => [
        'drupalSettings' => [
          'search_api_typesense' => [
            'api_key' => $configuration['admin_api_key'],
            'url' => \sprintf(
              '%s://%s:%s',
              $configuration['nodes'][0]['protocol'],
              $configuration['nodes'][0]['host'],
              $configuration['nodes'][0]['port'],
            ),
            'index' => $collection_name,
          ],
        ],
      ],
    ];

    return $build;
  }

  /**
   * Resolves the API key to use for the search.
   *
   * @param \Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend $backend
   *   The Typesense backend.
   *
   * @return string|null
   *   The resolved API key to use. NULL if not found.
   */
  private function resolveApiKey(SearchApiTypesenseBackend $backend): ?string {
    // Try to get the configured search-only key for this server.
    $config = $this->config('search_api_typesense.search_keys');
    $server_id = $backend->getServer()->id();
    $configured_search_key = $config->get("servers.{$server_id}.search_only_key");

    if ($configured_search_key !== NULL && $configured_search_key !== '') {
      $this->messenger()->addStatus(
        $this->t('Using configured search-only API key: @key', ['@key' => \substr($configured_search_key, 0, 8) . '...']),
      );
      return $configured_search_key;
    }

    // No search-only key configured, provide helpful guidance.
    $server_id = $backend->getServer()->id();
    $api_keys_link = Link::createFromRoute(
      $this->t('API keys management page'),
      'search_api_typesense.server.api_keys',
      ['search_api_server' => $server_id],
    );

    $this->messenger()->addWarning(
      $this->t('For better security, create a search-only key at the @api_keys_link, then configure it using the form below.', [
        '@api_keys_link' => $api_keys_link->toString(),
      ]),
    );

    return NULL;
  }

}
