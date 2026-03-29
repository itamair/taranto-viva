<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Plugin\search_api\backend;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api\Backend\BackendPluginBase;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api_typesense\Api\Config;
use Drupal\search_api_typesense\Api\SearchApiTypesenseException;
use Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException;
use Drupal\search_api_typesense\Api\TypesenseClient;
use Drupal\search_api_typesense\Api\TypesenseClientInterface;
use Drupal\search_api_typesense\DocumentSplitter\DocumentSplitterInterface;
use Drupal\search_api_typesense\Entity\TypesenseSchemaInterface;
use Drupal\search_api_typesense\Enum\FacetFieldType;
use Drupal\search_api_typesense\Event\TypesenseCollectionEvents;
use Drupal\search_api_typesense\Event\TypesenseCollectionNameEvent;
use Drupal\search_api_typesense\LoggerTrait;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class SearchApiTypesenseBackend.
 *
 * @SearchApiTypesenseBackend.
 *
 * @SearchApiBackend(
 *   id = "search_api_typesense",
 *   label = @Translation("Search API Typesense"),
 *   description = @Translation("Index items using Typesense server.")
 * )
 */
class SearchApiTypesenseBackend extends BackendPluginBase implements PluginFormInterface {

  use LoggerTrait;
  use PluginFormTrait;
  use StringTranslationTrait;

  const NODES_WRAPPER = 'typesense-individual-nodes';

  /**
   * The Typesense client.
   *
   * @var \Drupal\search_api_typesense\Api\TypesenseClientInterface
   */
  private TypesenseClientInterface $typesense;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  private Client $httpClient;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private ConfigFactoryInterface $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * The document splitter.
   *
   * @var \Drupal\search_api_typesense\DocumentSplitter\DocumentSplitterInterface
   */
  private DocumentSplitterInterface $documentSplitter;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private EventDispatcherInterface $eventDispatcher;

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
  ): SearchApiTypesenseBackend {
    $instance = parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition,
    );

    $http_client = $container->get('http_client');
    \assert($http_client instanceof Client);
    $instance->httpClient = $http_client;

    $config_factory = $container->get('config.factory');
    \assert($config_factory instanceof ConfigFactoryInterface);
    $instance->configFactory = $config_factory;

    $entity_type_manager = $container->get('entity_type.manager');
    \assert($entity_type_manager instanceof EntityTypeManagerInterface);
    $instance->entityTypeManager = $entity_type_manager;

    $document_splitter = $container->get(
      'search_api_typesense.document_splitter',
    );
    \assert($document_splitter instanceof DocumentSplitterInterface);
    $instance->documentSplitter = $document_splitter;

    $event_dispatcher = $container->get('event_dispatcher');
    \assert($event_dispatcher instanceof EventDispatcherInterface);
    $instance->eventDispatcher = $event_dispatcher;

    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-return array<array-key, mixed>
   */
  public function viewSettings(): array {
    $info = [];

    try {
      // Loop over indexes as it's possible for an index to not yet have a
      // corresponding collection.
      $num = 1;
      foreach ($this->getServer()->getIndexes() as $index) {
        $collection_name = $this->getCollectionName($index);
        $collection_info = $this
          ->getTypesenseClient()
          ->retrieveCollectionInfo($collection_name);

        if ($collection_info === NULL) {
          $info[] = [
            'label' => $this->t('Typesense collection @num: name', [
              '@num' => $num,
            ]),
            'info' => $this->t('Collection does not exist'),
          ];
          $num++;

          continue;
        }

        $info[] = [
          'label' => $this->t('Typesense collection @num: name', [
            '@num' => $num,
          ]),
          'info' => $collection_name,
        ];

        $collection_created = [
          'label' => $this->t('Typesense collection @num: created', [
            '@num' => $num,
          ]),
          'info' => NULL,
        ];

        $collection_documents = [
          'label' => $this->t('Typesense collection @num: documents', [
            '@num' => $num,
          ]),
          'info' => NULL,
        ];

        $collection_created['info'] = \date(
          DATE_ATOM,
          $collection_info['created_at'],
        );
        $collection_documents['info'] = $collection_info['num_documents'] > '0'
          ? \number_format($collection_info['num_documents'])
          : $this->t('no documents have been indexed');

        $info[] = $collection_created;
        $info[] = $collection_documents;

        $num++;
      }

      $typesense = $this->getTypesenseClient();

      $server_health = $typesense->retrieveHealth();

      $info[] = [
        'label' => $this->t('Typesense server health'),
        'info' => $server_health['ok'] ? 'ok' : 'not ok',
      ];

      try {
        $server_debug = $typesense->retrieveDebug();

        $info[] = [
          'label' => $this->t('Typesense server version'),
          'info' => $server_debug['version'],
        ];
      }
      catch (SearchApiTypesenseRequestUnauthorizedException $e) {
        $info[] = [
          'label' => $this->t('Typesense server version'),
          'info' => $e->getMessage(),
        ];
      }

      return $info;
    }
    catch (SearchApiTypesenseException $e) {
      $this->logError(
        $e,
        $this->t('Unable to retrieve server and/or index information'),
      );
    }

    return [];
  }

  /**
   * Returns the parameters for the collection-specific search.
   *
   * These parameters are used to configure the search query for a
   * specific Typesense collection. For more information on Typesense
   * search parameters, see:
   * https://github.com/typesense/typesense-instantsearch-adapter?tab=readme-ov-file#index.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index for which to retrieve the search parameters.
   *
   * @return array<string, string>
   *   The search parameters for the collection.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   */
  public function getCollectionSpecificSearchParameters(IndexInterface $index): array {
    $collection_name = $this->getCollectionName($index);
    $schema = $this->getSchemaForIndex($index)?->getSchema();

    if ($schema === NULL) {
      throw new SearchApiTypesenseException(
        \sprintf('Unable to retrieve schema for index %s', $index->label()),
      );
    }

    $typesense = $this->getTypesenseClient();

    return [
      'query_by' => \implode(
        ',',
        $typesense->getFieldsForQueryBy($collection_name),
      ),
      'query_by_weights' => isset($schema['fields'])
        ? \implode(',', $typesense->getQueryByWeight($schema['fields']))
        : '',
      'sort_by' => isset($schema['fields'])
        ? \implode(',', $typesense->getFieldsForSortBy($schema['fields']))
        : '',
    ];
  }

  /**
   * Returns the render parameters for the collection.
   *
   * This method retrieves the collection name, label, all fields, facet
   * number fields, facet string fields, and entity types for the given
   * Search API index. These parameters are used to render the HTML markup
   * and prepare the UI using the InstantSearch.js library.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index for which to retrieve the render parameters.
   *
   * @return array<string, mixed>
   *   The render parameters for the collection.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   */
  public function getCollectionRenderParameters(IndexInterface $index): array {
    $collection_name = $this->getCollectionName($index);
    $schema = $this->getSchemaForIndex($index)?->getSchema();

    if ($schema === NULL) {
      throw new SearchApiTypesenseException(
        \sprintf('Unable to retrieve schema for index %s', $index->label()),
      );
    }

    $typesense = $this->getTypesenseClient();

    return [
      'collection_name' => $collection_name,
      'collection_label' => $index->label(),
      'all_fields' => $typesense->getFields($collection_name),
      'entity_types' => $index->getEntityTypes(),
    ];
  }

  /**
   * Returns the facets for the given index.
   *
   * This method retrieves the facet fields identifying their type (number or
   * string) so that the frontend can render them using the appropriate
   * Typesense InstantSearch.js widgets.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index for which to retrieve the facets.
   *
   * @return array<int, array<string, string>>
   *   The facets for the collection, each containing
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   */
  public function getFacetsForCollection(IndexInterface $index): array {
    $collection_name = $this->getCollectionName($index);
    $schema = $this->getSchemaForIndex($index)?->getSchema();

    if ($schema === NULL) {
      throw new SearchApiTypesenseException(
        \sprintf('Unable to retrieve schema for index %s', $index->label()),
      );
    }

    $typesense = $this->getTypesenseClient();
    $facets = [];

    foreach (FacetFieldType::cases() as $facet_type) {
      $fields = $typesense->getFieldsForFacetType($collection_name, $facet_type);
      $facet_group = \array_map(
        static function ($field_name) use ($index, $facet_type) {
          return [
            'type' => $facet_type->value,
            'name' => $field_name,
            'label' => $index->getFields()[$field_name]->getLabel() ?? $field_name,
          ];
        },
        $fields,
      );
      $facets = \array_merge($facets, $facet_group);
    }

    return $facets;
  }

  /**
   * Returns Typesense auth credentials if ALL needed values are set.
   *
   * @param array<string, mixed> $configuration
   *   The backend configuration.
   *
   * @return \Drupal\search_api_typesense\Api\Config|null
   *   The Typesense client configuration, or NULL if not all required.
   */
  protected function getClientConfiguration(array $configuration): ?Config {
    $api_key_key = 'admin_api_key';

    if (isset($configuration[$api_key_key], $configuration['nodes'], $configuration['retry_interval_seconds'])) {
      return new Config(
        api_key               : $configuration[$api_key_key],
        nearest_node          : $configuration['nearest_node'] ?? NULL,
        nodes                 : \array_filter(
          $configuration['nodes'],
          static function ($key) {
            return \is_numeric($key);
          },
          ARRAY_FILTER_USE_KEY,
        ),
        retry_interval_seconds: \intval(
          $configuration['retry_interval_seconds'],
        ),
        http_client           : $this->httpClient,
      );
    }

    return NULL;
  }

  /**
   * Returns a Typesense schema.
   *
   * @param string $index_id
   *   The schema ID, which corresponds to the Search API index ID.
   *
   * @return \Drupal\search_api_typesense\Entity\TypesenseSchemaInterface|null
   *   A Typesense schema.
   *
   * @deprecated in search_api_typesense:1.0.2 and is removed from
   *    search_api_typesense:2.0.0. Use getSchemaForIndex() instead.
   * @see https://www.drupal.org/node/3528341
   */
  public function getSchema(string $index_id): ?TypesenseSchemaInterface {
    // phpcs:ignore -- Conflicting sniffs for FQDN requirement and @-prefix.
    @trigger_error('\Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend::getSchema() is deprecated in search_api_typesense:1.0.2 and is removed from search_api_typesense:2.0.0. Use ::getSchemaForIndex() instead. See https://www.drupal.org/node/3528341', E_USER_DEPRECATED);
    try {
      $index = $this->entityTypeManager
        ->getStorage('search_api_index')
        ->load($index_id);

      return $index instanceof IndexInterface ? $this->getSchemaForIndex($index) : NULL;
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      return NULL;
    }
  }

  /**
   * Returns the Typesense schema for the given index.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index for which to retrieve the schema.
   *
   * @return \Drupal\search_api_typesense\Entity\TypesenseSchemaInterface|null
   *   The Typesense schema.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   */
  public function getSchemaForIndex(IndexInterface $index): ?TypesenseSchemaInterface {
    if ($index->id() === NULL) {
      throw new SearchApiTypesenseException(
        \sprintf('Index %s has no ID', $index->label()),
      );
    }

    try {
      /** @var \Drupal\search_api_typesense\Entity\TypesenseSchemaInterface|null $typesense_schema */
      $typesense_schema = $this->entityTypeManager
        ->getStorage('typesense_schema')
        ->load($index->id());

      return $typesense_schema;
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      return NULL;
    }
  }

  /**
   * Synchronizes Typesense collection schemas with Search API indexes.
   *
   * When Search API indexes are created, there's not enough information to
   * create the corresponding collection (Typesense requires the full schema
   * to create a collection, and no fields will be defined yet when the Search
   * API index is created).
   *
   * Here, we make sure that there's an existing collection for every index.
   *
   * We don't need to verify the local schema and collection fields match since
   * the index will be marked in need of reindexing when the schema changes.
   *
   * Reindexing a Typesense collection always involves recreating it and doing
   * the indexing from scratch.
   *
   * We handle all this here instead of in the class's addIndex() method because
   * the index's fields and Typesense Schema must already be configured before
   * the collection can be created in the first place.
   */
  protected function syncIndexesAndCollections(): void {
    $indexes = $this->getServer()->getIndexes();

    try {
      // If there are no indexes, we have nothing to do.
      if (\count($indexes) === 0) {
        return;
      }

      // Loop over as many indexes as we have.
      foreach ($indexes as $index) {
        // Get the defined schema, updating the collection name.
        $typesense_schema = $this->getSchemaForIndex($index)?->getSchema();
        $collection_name = $this->getCollectionName($index);
        $typesense_schema['name'] = $collection_name;

        // If this index has no Typesense-specific properties defined in the
        // typesense_schema, there's nothing we CAN do here.
        //
        // Typesense has made the default_sorting_field setting optional, in
        // v0.20.0, so all we can really do is check for fields.
        if (!\array_key_exists('fields', $typesense_schema)) {
          return;
        }

        // Check to see if the collection corresponding to this index exists.
        $collection = $this
          ->getTypesenseClient()
          ->retrieveCollectionInfo($collection_name);

        // If it doesn't, create it.
        if ($collection === NULL) {
          $this->getTypesenseClient()->createCollection($typesense_schema);
        }
      }
    }
    catch (SearchApiException | SearchApiTypesenseException $e) {
      $this->logError(
        $e,
        $this->t('Unable to sync Search API index schema and Typesense schema'),
      );
    }
  }

  /**
   * Provides Typesense Server settings.
   *
   * @phpstan-param array<array-key, mixed> $form
   * @phpstan-return array<array-key, mixed>
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ): array {
    $form['#tree'] = TRUE;

    if (!$this->server->isNew() && \count($form_state->getUserInput()) === 0) {
      try {
        $this->isConfigured();
      }
      catch (\Exception $e) {
        $this->messenger()->addError($e->getMessage());
      }
    }

    $form['admin_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Admin API key'),
      '#maxlength' => 128,
      '#size' => 30,
      '#description' => $this->t(
        'A read-write API key for this Typesense instance. Required for indexing content. <strong>This key must be kept secret and never transmitted to the client. Ideally, it will be provided by an environment variable and never stored in version control systems</strong>.',
      ),
      '#default_value' => $this->configuration['admin_api_key'] ?? NULL,
      '#attributes' => [
        'placeholder' => '1234567890',
      ],
    ];

    $this->buildNearestNodeElement($form);
    $this->buildNodesElement($form, $form_state);
    $this->buildPublicEndpointElement($form);

    $form['retry_interval_seconds'] = [
      '#type' => 'number',
      '#title' => $this->t('Connection timeout (seconds)'),
      '#maxlength' => 2,
      '#size' => 10,
      '#description' => $this->t(
        'Time to wait before timing-out the connection attempt.',
      ),
      '#default_value' => $this->configuration['retry_interval_seconds'] ?? 2,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<array-key, mixed> $form
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    $values = $form_state->getValues();

    // Avoid saving the action element to the configuration.
    unset($values['nodes']['actions']);
    // Remove the nearest node if it's not enabled, because it's not required.
    // @see \Typesense\Lib\Configuration
    if (!$values['nearest_node_enable']) {
      $values['nearest_node'] = NULL;
    }

    $this->setConfiguration($values);
  }

  /**
   * {@inheritdoc}
   */
  public function removeIndex(/* IndexInterface $index */ $index): void {
    \assert($index instanceof IndexInterface);

    try {
      $collection_name = $this->getCollectionName($index);
      $this->getTypesenseClient()->dropCollection($collection_name);
    }
    catch (SearchApiTypesenseException $e) {
      $this->logError(
        $e,
        $this->t('Unable to remove index @index', ['@index' => $index->id()]),
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function indexItems(IndexInterface $index, array $items): array {
    $indexed_documents = [];
    $collection_name = $this->getCollectionName($index);

    $schema = $this->getSchemaForIndex($index);
    if ($schema === NULL) {
      throw new SearchApiTypesenseException(
        \sprintf('Unable to retrieve schema for index %s', $index->label()),
      );
    }

    // Loop over each indexable item.
    foreach ($items as $key => $item) {
      try {
        $document = [];

        // Add each value to the document.
        foreach ($item->getFields() as $field_name => $field) {
          $field_type = $field->getType();
          $field_values = $field->getValues();

          // Don't add empty fields to the document.
          if (\count($field_values) === 0) {
            continue;
          }

          // Rely on the Typesense service to enforce the
          // datatype.
          $value = $this->getTypesenseClient()->prepareItemValue(
          $field_values,
          $field_type,
          $field_name,
          );

          // 'id' is a reserved field name in Typesense. If the field name is
          // 'id', we change it to 'search_api_id'.
          if ($field_name === 'id') {
            $field_name = 'search_api_id';
          }

          $document[$field_name] = $value;
        }

        // Add the document ID.
        $document['id'] = $this
          ->getTypesenseClient()
          ->prepareId($key);

        // Create the document.
        if ($schema->isEmbeddingEnabled()) {
          // Delete all the previous chunks of the document, if any.
          // This is necessary because we are going to reindex the document,
          // and the chunks are going to be different.
          $this->getTypesenseClient()
            ->deleteDocuments(
              $collection_name,
              ['filter_by' => 'document_id:=' . $item->getId()],
            );

          $chunks = $this->documentSplitter->split(
            $this->getFieldsToEmbed($schema, $document),
            $this->getFieldsToPrependToAllChunks($schema),
            $document,
            $schema->get('chunk_size'),
            $schema->get('chunk_overlap_size'),
          );

          $document_id = $document['id'];

          foreach ($chunks as $chunk) {
            $document['chunk'] = $chunk->text;
            $document['document_id'] = $document_id;
            $document['id'] = \sprintf(
              '%s_%s',
              $document_id,
              $chunk->chunk_number,
            );
            $this
              ->getTypesenseClient()
              ->createDocument(
                $collection_name,
                $document,
              );
          }
        }
        else {
          $this
            ->getTypesenseClient()
            ->createDocument(
              $collection_name,
              $document,
            );
        }

        $indexed_documents[] = $key;
      }
      catch (SearchApiTypesenseException $e) {
        $this->logError(
          $e,
          $this->t('Unable to index items'),
        );
      }
    }

    return $indexed_documents;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItems(IndexInterface $index, array $item_ids): void {
    try {
      $collection_name = $this->getCollectionName($index);

      $schema = $this->getSchemaForIndex($index);
      if ($schema === NULL) {
        throw new SearchApiTypesenseException(
          \sprintf('Unable to retrieve schema for index %s', $index->label()),
        );
      }

      if ($schema->isEmbeddingEnabled()) {
        foreach ($item_ids as $item_id) {
          $this
            ->getTypesenseClient()
            ->deleteDocuments(
              $collection_name,
              ['filter_by' => 'document_id:=' . $item_id],
            );
        }
      }
      else {
        foreach ($item_ids as $item_id) {
          $this->getTypesenseClient()->deleteDocument($collection_name, $item_id);
        }
      }
    }
    catch (SearchApiTypesenseException $e) {
      $this->logError(
        $e,
        $this->t(
          'Unable to delete items @item',
          ['@item' => \implode(', ', $item_ids)],
        ),
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAllIndexItems(
    IndexInterface $index,
    $datasource_id = NULL,
  ): void {
    try {
      // The easiest way to remove all items is to drop the collection
      // altogether and then recreate it.
      //
      // This is especially the case, given that the only reason we ever want to
      // delete ALL items is to reindex which, in the case of Typesense, means
      // we are probably also changing the collection schema (which requires
      // deleting it) anyway.
      $collection_data = $this->getTypesenseClient()
        ->exportCollectionData($this->getCollectionName($index));
      $this->removeIndex($index);
      $this->syncIndexesAndCollections();
      $this->getTypesenseClient()
        ->importCollectionData(
          $this->getCollectionName($index),
          $collection_data,
        );
    }
    catch (SearchApiTypesenseException $e) {
      $this->logError(
        $e,
        $this->t('Unable to delete index @index', ['@index' => $index->id()]),
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function updateIndex(IndexInterface $index): void {
    $schema = $this->getSchemaForIndex($index);
    if ($schema === NULL) {
      return;
    }

    try {
      $index->reindex();

      $collection_data = $this
        ->getTypesenseClient()
        ->exportCollectionData($this->getCollectionName($index));

      $this->removeIndex($index);
      $this->syncIndexesAndCollections();

      $this
        ->getTypesenseClient()
        ->importCollectionData(
          $this->getCollectionName($index),
          $collection_data,
        );
    }
    catch (SearchApiTypesenseException $e) {
      $this->logError(
        $e,
        $this->t('Unable to update index @index', ['@index' => $index->id()]),
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function search(QueryInterface $query): void {
    // For query tagged with `server_index_status` the only thing we need to do
    // is to retrieve the number of documents in the collection.
    if ($query->hasTag('server_index_status')) {
      $collection_name = $this->getCollectionName($query->getIndex());

      $schema = $this->getSchemaForIndex($query->getIndex());
      if ($schema === NULL) {
        throw new SearchApiTypesenseException(
          \sprintf('Unable to retrieve schema for index %s', $query->getIndex()->label()),
        );
      }

      try {
        $info = $this
          ->getTypesenseClient()
          ->retrieveCollectionInfo($collection_name);

        if ($info === NULL) {
          throw new SearchApiTypesenseException(
            'Method retrieveCollectionInfo returned null.',
          );
        }

        $results = $query->getResults();
        $results->setResultCount($info['num_documents']);
      }
      catch (SearchApiTypesenseException $e) {
        $this->logError(
          $e,
          $this->t('Unable to retrieve collection info'),
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedFeatures(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getDiscouragedProcessors(): array {
    return [
      'snowball_stemmer',
      'stemmer',
      'stopwords',
      'tokenizer',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDataType($type): bool {
    return \str_starts_with($type, 'typesense_');
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    try {
      $this->getTypesenseClient();

      return $this->isConfigured();
    }
    catch (SearchApiTypesenseException | \Exception $e) {
      return FALSE;
    }
  }

  /**
   * Return the Typesense client.
   *
   * @return \Drupal\search_api_typesense\Api\TypesenseClientInterface
   *   The Typesense client.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   */
  public function getTypesenseClient(): TypesenseClientInterface {
    try {
      if (!$this->isConfigured()) {
        throw new SearchApiTypesenseException(
          'Typesense client is not configured.',
        );
      }

      if (isset($this->typesense)) {
        return $this->typesense;
      }

      $config = $this->getClientConfiguration($this->configuration);
      if ($config === NULL || !$config->valid()) {
        throw new SearchApiTypesenseException(
          'Typesense client configuration is invalid.',
        );
      }

      $this->typesense = new TypesenseClient($config);
      $this->typesense->retrieveHealth();
      $this->syncIndexesAndCollections();

      return $this->typesense;
    }
    catch (SearchApiTypesenseException $e) {
      $this->logError(
        $e,
        $this->t('Unable to retrieve the Typesense client'),
      );

      throw $e;
    }
  }

  /**
   * Callback for add another button.
   *
   * @param array<array-key, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<array-key, mixed>
   *   The updated form.
   *
   * @suppressWarning("PHPMD.UnusedFormalParameter")
   */
  public function nodeElementCallback(
    array &$form,
    FormStateInterface $form_state,
  ): array {
    // The work is already done in form(), where we rebuild the entity according
    // to the current form values and then create the backend configuration form
    // based on that. So we just need to return the relevant part of the form
    // here.
    return $form['backend_config']['nodes'];
  }

  /**
   * Submit handler for the "add-one-more" button.
   *
   * Increments the max counter and causes a rebuild.
   *
   * @param array<array-key, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @suppressWarning("PHPMD.UnusedFormalParameter")
   */
  public function addOneNodeElement(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    // Increase number of nodes count.
    $existing_nodes_count = $form_state->get('nodes_count');
    $form_state->set('nodes_count', ($existing_nodes_count + 1));

    // Since our buildForm() method relies on the value of 'nodes_count' to
    // generate 'node' form elements, we have to tell the form to rebuild. If we
    // don't do this, the form builder will not call buildForm().
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit handler for the "remove-the-last-node" button.
   *
   * Decrements the max counter and causes a rebuild.
   *
   * @param array<array-key, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function removeNodeElementCallback(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    // Decrease number of nodes count.
    $existing_nodes_count = $form_state->get('nodes_count');
    $index_to_remove = $existing_nodes_count - 1;
    $form_state->set('nodes_count', $index_to_remove);

    // Remove the node element.
    unset($form['backend_config']['nodes'][$index_to_remove]);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Return the collection name for the given index.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index.
   *
   * @return string
   *   The collection name.
   */
  public function getCollectionName(IndexInterface $index): string {
    $name = (string) $index->id();

    // Emit an event to allow altering the collection name.
    $event = new TypesenseCollectionNameEvent($index, $name);
    $this->eventDispatcher->dispatch($event, TypesenseCollectionEvents::ALTER_NAME);

    return $event->getCollectionName();
  }

  /**
   * Check if the backend is configured.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   */
  private function isConfigured(): bool {
    $server_configurations = $this
      ->configFactory
      ->get('search_api.server.' . $this->server->id());
    $server_configurations_with_overrides = $server_configurations->getOriginal(
      'backend_config',
    );

    $admin_api_key = $server_configurations_with_overrides['admin_api_key'] ?? NULL;

    // Generate warning if required configurations have not been defined yet.
    if ($admin_api_key === NULL) {
      throw new SearchApiTypesenseException(
        $this->t('No API keys have been defined yet.')
          ->render(),
      );
    }

    if ($this->configuration['nearest_node_enable'] ?? FALSE) {
      $host = $server_configurations_with_overrides['nearest_node']['host'] ?? NULL;
      $port = $server_configurations_with_overrides['nearest_node']['port'] ?? NULL;
      $protocol = $server_configurations_with_overrides['nearest_node']['protocol'] ?? NULL;
      if ($host === NULL || $port === NULL || $protocol === NULL) {
        throw new SearchApiTypesenseException(
          $this->t('Nearest node has not been configured yet.')
            ->render(),
        );
      }
    }

    $nodes_configured = TRUE;
    $num_nodes = \count($this->configuration['nodes']);
    for ($i = 0; $i < $num_nodes; $i++) {
      $host = $server_configurations_with_overrides['nodes'][$i]['host'] ?? NULL;
      $port = $server_configurations_with_overrides['nodes'][$i]['port'] ?? NULL;
      $protocol = $server_configurations_with_overrides['nodes'][$i]['protocol'] ?? NULL;
      if ($host === NULL || $port === NULL || $protocol === NULL) {
        $nodes_configured = FALSE;
      }
    }
    if ($nodes_configured === FALSE) {
      throw new SearchApiTypesenseException(
        $this->t('Nodes have not been configured yet.')
          ->render(),
      );
    }

    if (!isset($server_configurations_with_overrides['retry_interval_seconds'])) {
      throw new SearchApiTypesenseException(
        $this->t('Connection timeout has not been set yet.')
          ->render(),
      );
    }

    if (!\is_numeric(
      $server_configurations_with_overrides['retry_interval_seconds'],
    )) {
      throw new SearchApiTypesenseException(
        $this->t('Connection timeout must be a number.')
          ->render(),
      );
    }

    return TRUE;
  }

  /**
   * Build the nodes form element.
   *
   * @param array<array-key, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  private function buildNodesElement(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    $form['nodes'] = [
      '#type' => 'details',
      '#title' => $this->t('Individual nodes'),
      '#description' => $this->t('The Typesense server nodes.'),
      '#open' => TRUE,
      '#prefix' => '<div id="' . self::NODES_WRAPPER . '">',
      '#suffix' => '</div>',
    ];

    // Calculate the number of nodes.
    // If there is no "nodes_count" state value, count number of config nodes.
    // If there is no config nodes, set the number of nodes to 1, so there is
    // at least one form element.
    $nodes_count = $form_state->get('nodes_count');
    if ($nodes_count === NULL) {
      $nodes_count = \count($this->configuration['nodes'] ?? []);
      $nodes_count = $nodes_count > 0 ? $nodes_count : 1;
      $form_state->set('nodes_count', $nodes_count);
    }

    $form['nodes']['actions'] = [
      '#type' => 'actions',
    ];

    $form['nodes']['actions']['add_node'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add one more node'),
      '#submit' => [[$this, 'addOneNodeElement']],
      '#attributes' => [
        'class' => [
          'button',
          'button-action',
          'button--primary',
          'button--small',
        ],
      ],
      '#ajax' => [
        'callback' => [$this, 'nodeElementCallback'],
        'wrapper' => self::NODES_WRAPPER,
      ],
    ];
    // The server configuration needs at least one node.
    if ($nodes_count > 1) {
      $form['nodes']['actions']['remove_node'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove the last node'),
        '#submit' => [[$this, 'removeNodeElementCallback']],
        '#attributes' => [
          'class' => [
            'button',
            'button-action',
            'button--danger',
            'button--small',
          ],
        ],
        '#ajax' => [
          'callback' => [$this, 'nodeElementCallback'],
          'wrapper' => self::NODES_WRAPPER,
        ],
      ];
    }

    for ($i = 0; $i < $nodes_count; $i++) {
      $form['nodes'][$i] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Node @num', ['@num' => $i + 1]),
      ];

      $form['nodes'][$i]['host'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Host'),
        '#maxlength' => 128,
        '#description' => $this->t('The hostname for connecting to this node.'),
        '#default_value' => $this->configuration['nodes'][$i]['host'] ?? NULL,
        '#attributes' => [
          'placeholder' => 'typesense.example.com',
        ],
      ];

      $form['nodes'][$i]['port'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Port'),
        '#maxlength' => 5,
        '#description' => $this->t('The port for connecting to this node.'),
        '#default_value' => $this->configuration['nodes'][$i]['port'] ?? NULL,
        '#attributes' => [
          'placeholder' => '576',
        ],
      ];

      $form['nodes'][$i]['protocol'] = [
        '#type' => 'select',
        '#title' => $this->t('Protocol'),
        '#options' => [
          'http' => 'http',
          'https' => 'https',
        ],
        '#description' => $this->t('The protocol for connecting to this node.'),
        '#default_value' => $this->configuration['nodes'][$i]['protocol'] ?? 'https',
      ];
    }
  }

  /**
   * Build the nearest node form element.
   *
   * @param array<array-key, mixed> $form
   *   The form.
   */
  private function buildNearestNodeElement(array &$form): void {
    $form['nearest_node_enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable nearest node'),
      '#description' => $this->t('Enable the nearest node feature.'),
      '#default_value' => $this->configuration['nearest_node_enable'] ?? FALSE,
    ];

    $form['nearest_node'] = [
      '#type' => 'details',
      '#title' => $this->t('Nearest node'),
      '#description' => $this->t(
        'Geo load-balanced endpoint with automatic failover.',
      ),
      '#open' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="backend_config[nearest_node_enable]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['nearest_node']['host'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Host'),
      '#maxlength' => 128,
      '#description' => $this->t('The hostname for connecting to this node.'),
      '#default_value' => $this->configuration['nearest_node']['host'] ?? NULL,
      '#attributes' => [
        'placeholder' => 'typesense.example.com',
      ],
    ];

    $form['nearest_node']['port'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Port'),
      '#maxlength' => 5,
      '#description' => $this->t('The port for connecting to this node.'),
      '#default_value' => $this->configuration['nearest_node']['port'] ?? NULL,
      '#attributes' => [
        'placeholder' => '576',
      ],
    ];

    $form['nearest_node']['protocol'] = [
      '#type' => 'select',
      '#title' => $this->t('Protocol'),
      '#options' => [
        'http' => 'http',
        'https' => 'https',
      ],
      '#description' => $this->t('The protocol for connecting to this node.'),
      '#default_value' => $this->configuration['nearest_node']['protocol'] ?? 'https',
    ];
  }

  /**
   * Build the public endpoint form element.
   *
   * @param array<array-key, mixed> $form
   *   The form.
   */
  public function buildPublicEndpointElement(array &$form): void {
    $form['public_endpoint'] = [
      '#type' => 'details',
      '#title' => $this->t('Public endpoint'),
      '#open' => TRUE,
    ];

    $form['public_endpoint']['host'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Host'),
      '#maxlength' => 128,
      '#description' => $this->t(
        'The public endpoint for this Typesense instance.',
      ),
      '#default_value' => $this->configuration['public_endpoint']['host'] ?? NULL,
      '#attributes' => [
        'placeholder' => 'https://typesense.example.com',
      ],
    ];

    $form['public_endpoint']['port'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Public port'),
      '#maxlength' => 5,
      '#description' => $this->t(
        'The public port for connecting to this Typesense instance.',
      ),
      '#default_value' => $this->configuration['public_endpoint']['port'] ?? NULL,
      '#attributes' => [
        'placeholder' => '80',
      ],
    ];

    $form['public_endpoint']['protocol'] = [
      '#type' => 'select',
      '#title' => $this->t('Public protocol'),
      '#options' => [
        'http' => 'http',
        'https' => 'https',
      ],
      '#description' => $this->t('The public protocol for this Typesense instance.'),
      '#default_value' => $this->configuration['public_endpoint']['protocol'] ?? 'https',
    ];
  }

  /**
   * Return the list of fields to embed.
   *
   * @phpstan-param array<array-key, mixed> $document
   * @phpstan-return array<array-key, mixed>
   */
  private function getFieldsToEmbed(
    ?TypesenseSchemaInterface $schema,
    array $document,
  ): array {
    $embedding_fields = $schema?->get('embedding_fields') ?? [];
    $fields_to_chunk = [];
    foreach ($embedding_fields as $field => $enabled) {
      if (\array_key_exists($field, $document) && $enabled !== 0) {
        $fields_to_chunk[$field] = $document[$field];
      }
    }

    return $fields_to_chunk;
  }

  /**
   * Return the list of fields whose values should be present in all chunks.
   *
   * @phpstan-return array<array-key, string | int<0, 0>>
   */
  private function getFieldsToPrependToAllChunks(?TypesenseSchemaInterface $schema): array {
    return \array_filter(
      $schema?->get('chunk_prepend_fields') ?? [],
      static function ($field) {
        return $field !== 0;
      },
    );
  }

}
