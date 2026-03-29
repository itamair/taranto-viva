<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Plugin\Block;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\search_api\ServerInterface;
use Drupal\search_api_typesense\Api\SearchApiTypesenseException;
use Drupal\search_api_typesense\Enum\FacetFieldType;
use Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend;
use Drupal\search_api_typesense\Render\TypesenseSearchRenderContext;
use Drupal\search_api_typesense\Render\TypesenseSearchRenderServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a Typesense search block.
 */
#[Block(
  id: "search_api_typesense_search_block",
  admin_label: new TranslatableMarkup("Search block"),
  category: new TranslatableMarkup("Typesense"),
)]
class TypesenseSearchBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The name of the form element that triggers the AJAX callback.
   *
   * This is used to identify which element triggered the AJAX request in the
   * block form.
   */
  const TRIGGER_ELEMENT_NAME = 'settings[typesense][server]';

  /**
   * The block constructor.
   *
   * @param array<string, mixed> $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager service.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\search_api_typesense\Render\TypesenseSearchRenderServiceInterface $renderService
   *   The render service.
   * @param \Drupal\Component\Uuid\UuidInterface $uuidService
   *   The UUID service.
   */
  final public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly LoggerInterface $logger,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LanguageManagerInterface $languageManager,
    private readonly Request $request,
    private readonly TypesenseSearchRenderServiceInterface $renderService,
    private readonly UuidInterface $uuidService,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<array-key, mixed> $configuration
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    $request = $container->get('request_stack')->getCurrentRequest();
    if ($request === NULL) {
      throw new \RuntimeException('No request available');
    }

    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.channel.search_api_typesense'),
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
      $request,
      $container->get('search_api_typesense.render_service'),
      $container->get('uuid'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    // Grant access to users with the 'access content' permission.
    return AccessResult::allowedIfHasPermission($account, 'access content');
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-return array<array-key, mixed>
   */
  public function defaultConfiguration(): array {
    return [
      'server' => '',
      'collections' => [],
      'hits_per_page' => 9,
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<array-key, mixed> $form
   * @phpstan-return array<string, mixed>
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['typesense'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Typesense search settings'),
      '#description' => $this->t("Choose which Typesense's collection you want to use for search."),
      '#weight' => 10,
    ];

    $form['typesense']['server'] = [
      '#type' => 'select',
      '#title' => $this->t('Server'),
      '#description' => $this->t('Select which server you want to use for search.'),
      '#default_value' => $this->configuration['server'] ?? NULL,
      '#options' => \array_map(
        static fn(ServerInterface $server) => $server->label(),
        $this->getAvailableTypesenseServers(),
      ),
      '#empty_option' => $this->t('- Select a server -'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'updateCollectionsCallback'],
        'wrapper' => 'collections-wrapper',
        'event' => 'change',
        'effect' => 'fade',
      ],
    ];

    $form['typesense']['collections_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'collections-wrapper'],
    ];
    $form['typesense']['collections_wrapper']['collections'] = $this->buildCollectionFormElement($form_state);
    $form['typesense']['collections_wrapper']['federated_search'] = [
      '#type' => 'item',
      '#title' => $this->t('Federated search (multi-index search)'),
      '#description' => $this->t("Choosing multiple collections you are enabling
        a federated search (multi-index search) with Typesense InstantSearch Adapter.
        This means that you are allowing users to search across all selected
        collections at once. If you need to perform a faceted search across
        multiple collections ensure that all collections have the same facet
        fields configured. For example, if you include tag_name in your schema
        configuration, it tells Typesense to compute facets for tag_name across
        all the collections included in the search. If one of those collections
        doesn't have tag_name as a facet field, Typesense rightfully throws an error."),
    ];

    $keys_doc_link = Link::fromTextAndUrl(
      $this->t('API keys documentation'),
      Url::fromUri('https://typesense.org/docs/0.24.1/api/api-keys.html#create-an-api-key', [
        'attributes' => ['target' => '_blank'],
      ]),
    );

    // Only create the admin keys link if a server is configured.
    $admin_keys_link = NULL;
    if (isset($this->configuration['server']) && $this->configuration['server'] !== '') {
      $admin_keys_link = Link::fromTextAndUrl(
        $this->t('API keys configuration'),
        Url::fromRoute('search_api_typesense.server.api_keys', [
          'search_api_server' => $this->configuration['server'],
        ], [
          'query' => ['destination' => $this->request->getRequestUri()],
        ]),
      );
    }

    $description = $this->t('Enter the search-only API key for Typesense. Form more information about search-only API keys, please refer to the @doc.', [
      '@doc' => $keys_doc_link->toString(),
    ]);

    if ($admin_keys_link !== NULL) {
      $description = $this->t('Enter the search-only API key for Typesense. Form more information about search-only API keys, please refer to the @doc.
         Please visit the @link to add the missing API key, then grab the key value from the response and paste it in the field above.', [
           '@doc' => $keys_doc_link->toString(),
           '@link' => $admin_keys_link->toString(),
         ]);
    }

    $form['typesense']['search_only_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search-only API Key'),
      '#description' => $description,
      '#default_value' => $this->configuration['search_only_key'] ?? '',
      '#required' => TRUE,
    ];

    $form['typesense']['hits_per_page'] = [
      '#type' => 'number',
      '#title' => $this->t('Items per page'),
      '#description' => $this->t('The number of items to display per page in the search results.'),
      '#default_value' => $this->configuration['hits_per_page'],
      '#min' => 1,
      '#max' => 100,
    ];

    return $form;
  }

  /**
   * AJAX callback to update the collections based on the selected server.
   *
   * @phpstan-param array<string, mixed> $form
   * @phpstan-return array<string, mixed>
   */
  public function updateCollectionsCallback(array &$form, FormStateInterface $form_state): array {
    // Return the collections wrapper which contains the collections' element.
    return $form['settings']['typesense']['collections_wrapper'];
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<array-key, mixed> $form
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    try {
      $server_id = $form_state->getValue(['typesense', 'server']);
      $server = $this->getServerStorage()->load($server_id);
      \assert($server instanceof ServerInterface);
      $backend = $this->renderService->validateBackend($server);
    }
    catch (SearchApiTypesenseException $e) {
      $this->logger->error($e->getMessage());
      $form_state->setErrorByName(
        name: 'typesense][server',
        message: $this->t('No Typesense server configured. Please configure a
          Typesense server in the Search API settings.'),
      );
      return;
    }

    $typesense_client = $backend->getTypesenseClient();
    $collections = $this->getSelectedCollections($form_state);
    // If you select more than one collection, we need to ensure that
    // the collections facet fields are compatible with each other.
    //
    // When you perform a federated search (multi-index search) with Typesense
    // InstantSearch Adapter, the adapter sends a single search request to
    // Typesense that targets multiple collections. If you include tag_name in
    // your faceting.facets configuration for InstantSearch, it tells Typesense
    // to compute facets for tag_name across all the collections included in the
    // search. If one of those collections doesn't have tag_name as a facet
    // field, Typesense rightfully throws an error.
    if (\count($collections) > 1) {
      $first_collection_facets = NULL;
      /** @var \Drupal\search_api\IndexInterface[] $search_api_indexes */
      $search_api_indexes = $this->getIndexStorage()->loadMultiple($collections);

      foreach ($search_api_indexes as $search_api_index) {
        $collection_name = $backend->getCollectionName($search_api_index);
        $current_facets = [];
        foreach (FacetFieldType::cases() as $facet_type) {
          $current_facets[$facet_type->value] = $typesense_client->getFieldsForFacetType($collection_name, $facet_type);
          \sort($current_facets[$facet_type->value]);
        }

        if ($first_collection_facets === NULL) {
          $first_collection_facets = $current_facets;
          continue;
        }

        if ($first_collection_facets !== $current_facets) {
          $form_state->setErrorByName(
            name: 'typesense][collections',
            message: $this->t('When selecting multiple collections for a
              federated search, all collections must have the same facet fields
              configured. Please check the @link.', [
                '@link' => Link::createFromRoute(
                    text: $this->t('indexes schema configuration'),
                    route_name:  'search_api.overview',
                )->toString(),
              ],
            ),
          );
          break;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<array-key, mixed> $form
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['server'] = $form_state->getValue(['typesense', 'server']);
    $this->configuration['collections'] = $this->getSelectedCollections($form_state);
    $this->configuration['hits_per_page'] = $form_state->getValue(['typesense', 'hits_per_page']);
    $this->configuration['search_only_key'] = $form_state->getValue(['typesense', 'search_only_key']);
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-return array<array-key, mixed>
   */
  public function build(): array {
    try {
      $server_id = $this->configuration['server'];
      $server = $this->getServerStorage()->load($server_id);
      \assert($server instanceof ServerInterface);
      $backend = $this->renderService->validateBackend($server);
    }
    catch (SearchApiTypesenseException $e) {
      $this->logger->error($e->getMessage());
      $this->messenger()->addError(
        $this->t('No Typesense server configured. Please configure a Typesense
          server in the Search API settings.'),
      );

      return [];
    }

    if (!$backend->isAvailable()) {
      $this->messenger()->addError(
        $this->t('The backend server :name is not available.', [
          ':name' => $this->configuration['name'] ?? 'Typesense',
        ]),
      );

      return [];
    }

    try {
      /** @var \Drupal\search_api\IndexInterface[] $search_api_indexes */
      $search_api_indexes = $this->getIndexStorage()
        ->loadMultiple($this->configuration['collections']);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger->error($e->getMessage());
      $this->messenger()->addError($this->t('An error occurred while retrieving the Typesense configuration. Please check the logs for more details.'));

      return [];
    }

    // This is a backward compatibility check for the search-only API key.
    if (!isset($this->configuration['search_only_key'])) {
      $this->messenger()->addError(
        $this->t('The search-only API key is not configured. Please configure it in the block settings.'),
      );

      return [];
    }

    // Create the render context for the block.
    $context = new TypesenseSearchRenderContext(
      backend: $backend,
      indexes: $search_api_indexes,
      apiKey: $this->configuration['search_only_key'],
      blockUuid: $this->uuidService->generate(),
      hitsPerPage: (int) $this->configuration['hits_per_page'],
      currentLanguage: $this->getCurrentLanguage()->getId(),
    );

    try {
      return $this->renderService->buildSearchRender($context);
    }
    catch (SearchApiTypesenseException $e) {
      $this->logger->error($e->getMessage());
      $this->messenger()->addError($this->t('An error occurred while rendering the search interface. Please check the logs for more details.'));

      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    // Merge parent cache contexts with language-specific contexts.
    return \array_merge(parent::getCacheContexts(), [
      'languages:language_url',
      'user.permissions',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    $cache_tags = parent::getCacheTags();

    // Server configuration changes.
    if (isset($this->configuration['server']) && $this->configuration['server'] !== '') {
      $cache_tags[] = 'config:search_api.server.' . $this->configuration['server'];
    }

    // Index/collection configuration changes.
    if (isset($this->configuration['collections']) && \is_array($this->configuration['collections'])) {
      foreach ($this->configuration['collections'] as $collection_id) {
        $cache_tags[] = 'config:search_api.index.' . $collection_id;
        $cache_tags[] = 'config:search_api.typesense_schema.' . $collection_id;
      }
    }

    // Typesense configuration changes.
    $cache_tags[] = 'config:search_api_typesense.settings';

    return $cache_tags;
  }

  /**
   * Returns the available Typesense collections.
   *
   * @param string $server_id
   *   The ID of the Typesense server.
   *
   * @return array<int|string, TranslatableMarkup|string|null>
   *   An associative array of collection IDs and labels.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getAvailableTypesenseCollections(string $server_id): array {
    $collections = [];
    $indexes = $this->getIndexStorage()->loadByProperties(
      ['server' => $server_id],
    );

    foreach ($indexes as $index) {
      if ($index->access('view')) {
        $collections[$index->id()] = $index->label();
      }
    }

    return $collections;
  }

  /**
   * Returns the available Typesense servers.
   *
   * @return \Drupal\search_api\ServerInterface[]
   *   The Typesense servers.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   */
  private function getAvailableTypesenseServers(): array {
    /** @var \Drupal\search_api\ServerInterface[] $servers */
    $servers = $this->getServerStorage()->loadMultiple();

    return \array_filter($servers, static fn(ServerInterface $server) => $server->getBackend() instanceof SearchApiTypesenseBackend);
  }

  /**
   * Returns the Search API index storage.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The Search API index storage.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getServerStorage(): EntityStorageInterface {
    return $this->entityTypeManager->getStorage('search_api_server');
  }

  /**
   * Returns the Search API index storage.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The Search API index storage.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getIndexStorage(): EntityStorageInterface {
    return $this->entityTypeManager->getStorage('search_api_index');
  }

  /**
   * Returns the current language.
   *
   * @return \Drupal\Core\Language\LanguageInterface
   *   The current language.
   */
  private function getCurrentLanguage(): LanguageInterface {
    return $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_URL);
  }

  /**
   * Returns the selected collections from the form state.
   *
   * The 'checkboxes' form element returns an associative array where the keys
   * are the collection IDs and the values are either the collection ID (if
   * checked) or 0 (if unchecked).
   *
   * For example:
   * @code
   * [
   *   'articles' => 'articles',
   *   'recipes' => 0,
   *   'test_index' => 0,
   * ]
   * @endcode
   *
   * This method filters that array to return a simple array of the selected
   * collection IDs.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<int, string>
   *   An array of selected collection IDs.
   */
  private function getSelectedCollections(FormStateInterface $form_state): array {
    $collections = $form_state->getValue(['typesense', 'collections_wrapper', 'collections']) ?? [];

    return \array_keys(\array_filter($collections, static fn($value) => $value !== 0));
  }

  /**
   * Builds the collections form element.
   *
   * This method is responsible for rendering the collections checkboxes based
   * on the selected Typesense server.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<string, mixed>
   *   A renderable array for the collections checkboxes.
   */
  private function buildCollectionFormElement(FormStateInterface $form_state): array {
    $selected_server = $this->getSelectedServer($form_state);

    if ($selected_server === NULL) {
      $this->logger->warning('No Typesense server selected for the search block.');

      return [
        '#type' => 'markup',
        '#markup' => '<div class="messages messages--warning">'
        . $this->t('Please select a server first.')
        . '</div>',
      ];
    }

    try {
      $collections = $this->getAvailableTypesenseCollections($selected_server);

      if (\count($collections) === 0) {
        $this->logger->warning('No collections found for the selected Typesense server: @server', ['@server' => $selected_server]);

        return [
          '#type' => 'markup',
          '#markup' => '<div class="messages messages--warning">'
          . $this->t('No collections available for the selected server.')
          . '</div>',
        ];
      }

      // For initial load, use valid collections from configuration.
      $default_collections = [];
      $triggering_element = $form_state->getTriggeringElement();
      if ($triggering_element === NULL || $triggering_element['#name'] !== self::TRIGGER_ELEMENT_NAME) {
        $configured_collections = $this->configuration['collections'] ?? [];
        $default_collections = \array_intersect($configured_collections, \array_keys($collections));
      }

      return [
        '#type' => 'checkboxes',
        '#title' => $this->t('Collections'),
        '#description' => $this->t('Choose the collections where you want to search.'),
        '#options' => $collections,
        '#default_value' => $default_collections,
        '#required' => TRUE,
      ];
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());

      return [
        '#type' => 'markup',
        '#markup' => '<div class="messages messages--error">'
        . $this->t('Error loading collections: @message', ['@message' => $e->getMessage()])
        . '</div>',
      ];
    }
  }

  /**
   * Returns the selected server from the form state.
   *
   * This method handles both initial form load and AJAX rebuilds.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string|null
   *   The selected server ID, or NULL if no server is selected.
   */
  private function getSelectedServer(FormStateInterface $form_state): ?string {
    $user_input = $form_state->getUserInput();

    // Check if this is an AJAX rebuild and get the triggering element.
    $triggering_element = $form_state->getTriggeringElement();
    if (
      $triggering_element !== NULL
      && isset($triggering_element['#name'])
      && $triggering_element['#name'] === self::TRIGGER_ELEMENT_NAME
    ) {
      // Server was just changed - get the new value from user input instead
      // of form values.
      $selected_server = $user_input['settings']['typesense']['server'] ?? NULL;

      // CRITICAL: Clear the collections from user input to prevent validation
      // errors.
      if (isset($user_input['settings']['typesense']['collections_wrapper']['collections'])) {
        unset($user_input['settings']['typesense']['collections_wrapper']['collections']);
        $form_state->setUserInput($user_input);
      }
    }
    else {
      // Not an AJAX rebuild or different trigger - use configuration.
      $selected_server = $this->configuration['server'] ?? NULL;
    }

    return $selected_server;
  }

}
