<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Api;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api_typesense\Enum\FacetFieldType;
use Http\Client\Exception;
use Typesense\Client;
use Typesense\Collection;
use Typesense\Exceptions\ConfigError;
use Typesense\Exceptions\ObjectNotFound;
use Typesense\Exceptions\RequestUnauthorized;
use Typesense\Exceptions\TypesenseClientError;

/**
 * The Search Api Typesense client.
 */
class TypesenseClient implements TypesenseClientInterface {

  use StringTranslationTrait;

  const CONVERSATION_HISTORY_COLLECTION_NAME = 'conversation_store';

  private Client $client;

  /**
   * TypesenseClient constructor.
   *
   * @param \Drupal\search_api_typesense\Api\Config $config
   *   The Typesense config.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   */
  public function __construct(Config $config) {
    try {
      $this->client = new Client($config->toArray());
    }
    catch (ConfigError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveCollections(): array {
    try {
      return $this->client->collections->retrieve();
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function searchDocuments(
    string $collection_name,
    array $parameters,
  ): array {
    try {
      if ($collection_name === '' || $parameters === []) {
        return [];
      }

      $collection = $this->retrieveCollection($collection_name);

      if ($collection === NULL) {
        throw new TypesenseClientError('Collection not found.');
      }

      return $collection->documents->search($parameters);
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('documents:search');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function multiSearch(
    array $searches,
    array $query_params = [],
    bool $union = FALSE,
  ): array {
    try {
      $results = $this->client->getMultiSearch()->perform(
        searches: [
          'union' => $union,
          'searches' => $searches,
        ],
        queryParameters: $query_params,
      );
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }

    if (isset($results['results']) && \is_array($results['results'])) {
      $results = $results['results'];
    }

    // In case of Union search the results aren't wrapped in an array.
    // Add a wrapper to normalize the result.
    if (!isset($results[0]) || !\is_array($results[0])) {
      $results = [$results];
    }

    foreach ($results as $result) {
      if (isset($result['code']) && (int) $result['code'] >= 400) {
        throw new SearchApiTypesenseException(
          message: $result['error'],
          code: $result['code'],
        );
      }
    }

    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function createCollection(array $schema): Collection {
    try {
      $this->client->collections->create($schema);
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('collections:create');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }

    return $this->retrieveCollection($schema['name']);
  }

  /**
   * {@inheritdoc}
   */
  public function dropCollection(?string $collection_name): void {
    try {
      $collections = $this->client->collections;
      if ($collections->offsetExists($collection_name)) {
        $collections[$collection_name]->delete();
      }
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('collections:delete');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createDocument(
    string $collection_name,
    array $document,
  ): void {
    try {
      $collection = $this->retrieveCollection($collection_name);

      if ($collection === NULL) {
        throw new TypesenseClientError('Collection not found.');
      }

      $collection->documents->upsert($document);
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('documents:upsert');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveDocument(
    string $collection_name,
    string $id,
  ): array | null {
    try {
      $collection = $this->retrieveCollection($collection_name);

      if ($collection === NULL) {
        throw new TypesenseClientError('Collection not found.');
      }

      return $collection
        ->documents[$this->prepareId($id)]->retrieve();
    }
    catch (ObjectNotFound $e) {
      return NULL;
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('documents:get');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteDocument(string $collection_name, string $id): array {
    try {
      $collection = $this->retrieveCollection($collection_name);

      if ($collection === NULL) {
        throw new TypesenseClientError('Collection not found.');
      }

      $typesense_id = $this->prepareId($id);

      $document = $this->retrieveDocument($collection_name, $id);
      if ($document !== NULL) {
        return $collection->documents[$typesense_id]->delete();
      }

      return [];
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('documents:delete');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteDocuments(
    string $collection_name,
    array $filter_condition,
  ): array {
    try {
      $collection = $this->retrieveCollection($collection_name);

      if ($collection === NULL) {
        throw new TypesenseClientError('Collection not found.');
      }

      return $collection->documents->delete($filter_condition);
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('documents:delete');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createSynonym(
    string $collection_name,
    string $id,
    array $synonym,
  ): array {
    try {
      $collection = $this->retrieveCollection($collection_name);

      if ($collection === NULL) {
        throw new TypesenseClientError('Collection not found.');
      }

      return $collection->synonyms->upsert($id, $synonym);
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('synonyms:create');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveSynonym(string $collection_name, string $id): array {
    try {
      $collection = $this->retrieveCollection($collection_name);

      if ($collection === NULL) {
        throw new TypesenseClientError('Collection not found.');
      }

      return $collection->synonyms[$id]->retrieve();
    }
    catch (ObjectNotFound $e) {
      throw new SearchApiTypesenseException(
        $this->t('Synonym not found.')->render(),
      );
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('synonyms:get');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveSynonyms(string $collection_name): array {
    try {
      $collection = $this->retrieveCollection($collection_name);

      if ($collection === NULL) {
        throw new TypesenseClientError('Collection not found.');
      }

      return $collection->synonyms->retrieve();
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('synonyms:list');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteSynonym(string $collection_name, string $id): array {
    try {
      $collection = $this->retrieveCollection($collection_name);

      if ($collection === NULL) {
        throw new TypesenseClientError('Collection not found.');
      }

      return $collection->synonyms[$id]->delete();
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('synonyms:delete');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createCuration(
    string $collection_name,
    string $id,
    array $curation,
  ): array {
    try {
      $collection = $this->retrieveCollection($collection_name);

      if ($collection === NULL) {
        throw new TypesenseClientError('Collection not found.');
      }

      return $collection->overrides->upsert($id, $curation);
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('overrides:create');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveCuration(string $collection_name, string $id): array {
    try {
      $collection = $this->retrieveCollection($collection_name);

      if ($collection === NULL) {
        throw new TypesenseClientError('Collection not found.');
      }

      return $collection->overrides[$id]->retrieve();
    }
    catch (ObjectNotFound $e) {
      throw new SearchApiTypesenseException(
        $this->t('Curation not found.')->render(),
      );
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('overrides:get');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveCurations(string $collection_name): array {
    try {
      $collection = $this->retrieveCollection($collection_name);

      if ($collection === NULL) {
        throw new TypesenseClientError('Collection not found.');
      }

      return $collection->overrides->retrieve();
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('overrides:list');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteCuration(string $collection_name, string $id): array {
    try {
      $collection = $this->retrieveCollection($collection_name);

      if ($collection === NULL) {
        throw new TypesenseClientError('Collection not found.');
      }

      return $collection->overrides[$id]->delete();
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('overrides:delete');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createConversationModel(
    array $params,
  ): array {
    try {
      return $this->client->conversations->getModels()->create($params);
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('models:create');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveConversationModel(string $id): array {
    try {
      return $this->client->conversations->getModels()[$id]->retrieve();
    }
    catch (ObjectNotFound $e) {
      throw new SearchApiTypesenseException(
        $this->t('Conversation model not found.')->render(),
      );
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('models:get');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveConversationModels(): array {
    try {
      return $this->client->conversations->getModels()->retrieve();
    }
    catch (ObjectNotFound $e) {
      return [];
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('models:get');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function updateConversationModel(string $id, array $params): array {
    try {
      return $this->client->conversations->getModels()[$id]->update($params);
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('models:update');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteConversationModel(string $id): array {
    try {
      return $this->client->conversations->getModels()[$id]->delete();
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('models:delete');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function hasConversationHistoryCollection(): bool {
    return $this->retrieveCollection(
      TypesenseClient::CONVERSATION_HISTORY_COLLECTION_NAME,
      FALSE,
      ) !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function ensureConversationHistoryCollection(): void {
    try {
      $conversation_store = $this
        ->retrieveCollection(
          TypesenseClient::CONVERSATION_HISTORY_COLLECTION_NAME,
          FALSE,
        );

      if ($conversation_store === NULL) {
        $this->client->collections->create([
          'name' => 'conversation_store',
          'fields' => [
            [
              'name' => 'conversation_id',
              'type' => 'string',
            ],
            [
              'name' => 'model_id',
              'type' => 'string',
            ],
            [
              'name' => 'timestamp',
              'type' => 'int32',
            ],
            [
              'name' => 'role',
              'type' => 'string',
              'index' => FALSE,
            ],
            [
              'name' => 'message',
              'type' => 'string',
              'index' => FALSE,
            ],
          ],
        ]);
      }
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-return array{created_at: string, num_documents: int} | null
   */
  public function retrieveCollectionInfo(
    string $collection_name,
  ): array | null {
    try {
      $collection = $this->retrieveCollection($collection_name, FALSE);

      if ($collection === NULL) {
        return NULL;
      }

      $collection_data = $collection->retrieve();

      return [
        'created_at' => $collection_data['created_at'],
        'num_documents' => $collection_data['num_documents'],
      ];
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('collections:get');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveHealth(): array {
    try {
      return $this->client->health->retrieve();
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveDebug(): array {
    try {
      return $this->client->debug->retrieve();
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('debug:list');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveMetrics(): array {
    try {
      return $this->client->metrics->retrieve();
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('metrics.json:list');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createKey(array $schema): array {
    try {
      return $this->client->keys->create($schema);
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('keys:create');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveKey(int $key_id): array {
    try {
      $key = $this->client->keys[$key_id];

      return $key->retrieve();
    }
    catch (ObjectNotFound $e) {
      throw new SearchApiTypesenseException(
        $this->t('Key not found.')->render(),
      );
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('keys:get');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveKeys(): array {
    try {
      return $this->client->getKeys()->retrieve();
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('keys:get');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteKey(int $key_id): array {
    try {
      $key = $this->client->keys[$key_id];

      return $key->delete();
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('keys:delete');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createStopword(array $schema): array {
    try {
      return $this->client->stopwords->put($schema);
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('stopwords:create');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveStopword(string $name): array {
    try {
      return $this->client->stopwords->get($name);
    }
    catch (ObjectNotFound $e) {
      throw new SearchApiTypesenseException(
        $this->t('Stopword set not found.')->render(),
      );
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('stopwords:get');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveStopwords(): array {
    try {
      return $this->client->stopwords->getAll();
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('stopwords:list');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteStopword(string $name): array {
    try {
      return $this->client->stopwords->delete($name);
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('stopwords:delete');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function importCollectionData(
    string $collection_name,
    array $data,
  ): void {
    try {
      $collection = $this->retrieveCollection($collection_name);

      if ($collection === NULL) {
        throw new TypesenseClientError('Collection not found.');
      }

      foreach ($data['synonyms'] as $synonym) {
        $collection->synonyms->upsert($synonym['id'], $synonym);
      }

      foreach ($data['curations'] as $curation) {
        $collection->overrides->upsert($curation['id'], $curation);
      }
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('synonyms:create, overrides:create');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function exportCollectionData(
    string $collection_name,
    bool $include_schema = FALSE,
  ): array {
    try {
      $collection = $this->retrieveCollection($collection_name);

      if ($collection === NULL) {
        throw new TypesenseClientError('Collection not found.');
      }

      $data = [
        'synonyms' => $collection->synonyms->retrieve()['synonyms'],
        'curations' => $collection->overrides->retrieve()['overrides'],
      ];

      if ($include_schema) {
        $schema = $collection->retrieve();
        unset($schema['created_at']);
        unset($schema['num_documents']);

        $data['schema'] = $schema;
      }

      return $data;
    }
    catch (RequestUnauthorized $e) {
      throw new SearchApiTypesenseRequestUnauthorizedException('synonyms:get, overrides:get');
    }
    catch (Exception | TypesenseClientError $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function prepareId(string $id): string {
    // TypeSense does not allow characters that require encoding in urls. The
    // Search API ID has "/" character in it that is not compatible with that
    // requirement. So replace that with an underscore.
    // @see https://typesense.org/docs/latest/api/documents.html#index-a-document.
    return \str_replace('/', '-', $id);
  }

  /**
   * {@inheritdoc}
   */
  public function prepareItemValue(
    array $value,
    string $type,
    string $field_name,
  ): bool | float | int | string | array {
    // In Drupal every field is represented as an array. If the field is an
    // array with only one value, then we can use that value as the field value.
    // Except for the TypeSense types that require an array value.
    if (!\str_contains($type, '[]')) {
      if (\count($value) > 1) {
        throw new SearchApiTypesenseException(
          $this->t(
            'The field @name type @type does not support multiple values.',
            ['@name' => $field_name, '@type' => $type],
          )->render(),
        );
      }
      else {
        $value = \reset($value);
      }
    }

    switch ($type) {
      case 'typesense_bool[]':
        if (!$value) {
          $value = [];
        }
        else {
          $v = [];
          foreach ($value as $item) {
            $v[] = (bool) $item;
          }
          $value = $v;
        }
        break;

      case 'typesense_float[]':
        if (!$value) {
          $value = [];
        }
        else {
          $v = [];
          foreach ($value as $item) {
            $v[] = (float) $item;
          }
          $value = $v;
        }
        break;

      case 'typesense_int32[]':
      case 'typesense_int64[]':
        if (!$value) {
          $value = [];
        }
        else {
          $v = [];
          foreach ($value as $item) {
            $v[] = (int) $item;
          }
          $value = $v;
        }
        break;

      case 'typesense_string[]':
        if (!$value) {
          $value = [];
        }
        else {
          $v = [];
          foreach ($value as $item) {
            $v[] = (string) $item;
          }
          $value = $v;
        }
        break;
    }

    // Throw an exception if an unsupported type is encountered.
    if (
      !\is_bool($value) &&
      !\is_float($value) &&
      !\is_int($value) &&
      !\is_string($value) &&
      !\is_array($value)
    ) {
      throw new SearchApiTypesenseException(
        $this->t(
          'Unsupported value of type %actual_type, expected %type.',
          [
            '%actual_type' => \gettype($value),
            '%type' => $type,
          ],
        )->render(),
      );
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getFields(string $collection_name): array {
    try {
      $collection = $this->retrieveCollection($collection_name);
      if ($collection === NULL) {
        return [];
      }

      $schema = $collection->retrieve();

      return \array_map(static function (array $field) {
        return $field['name'];
      }, $schema['fields']);
    }
    catch (Exception | TypesenseClientError | SearchApiTypesenseException $e) {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldsForQueryBy(string $collection_name): array {
    try {
      $collection = $this->retrieveCollection($collection_name);
      if ($collection === NULL) {
        return [];
      }

      $schema = $collection->retrieve();

      return \array_map(static function (array $field) {
        return $field['name'];
      }, \array_filter($schema['fields'], static function ($field) {
        return \in_array($field['type'], ['string', 'string[]'], TRUE) && $field['index'];
      }));
    }
    catch (Exception | TypesenseClientError | SearchApiTypesenseException $e) {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldsForFacetType(string $collection_name, FacetFieldType $facet_type): array {
    try {
      $collection = $this->retrieveCollection($collection_name);
      if ($collection === NULL) {
        return [];
      }
      $schema = $collection->retrieve();
      $types = $facet_type->types();
      return \array_values(\array_map(static function (array $field) {
        return $field['name'];
      }, \array_filter($schema['fields'], static function ($field) use ($types) {
        return $field['facet'] === TRUE && \in_array($field['type'], $types, TRUE);
      })));
    }
    catch (Exception | TypesenseClientError | SearchApiTypesenseException $e) {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryByWeight(array $fields): array {
    try {
      return \array_map(static function (array $field) {
        return $field['weight'];
      }, \array_filter($fields, static function ($field) {
        return \in_array($field['type'], ['string', 'string[]'], TRUE) && $field['index'];
      }));
    }
    catch (Exception | TypesenseClientError | SearchApiTypesenseException $e) {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldsForSortBy(array $fields): array {
    try {
      $filteredFields = \array_filter($fields, static function ($field) {
        return isset($field['index'], $field['sort']) && $field['index'] && $field['sort'];
      });

      return \array_map(static function ($field) {
        return $field['name'] . ':' . $field['sort_type'];
      }, $filteredFields);
    }
    catch (Exception | TypesenseClientError | SearchApiTypesenseException $e) {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function generateScopedSearchKey(
    string $key,
    array $parameters,
  ): string {
    try {
      return $this->client->keys->generateScopedSearchKey($key, $parameters);
    }
    catch (\JsonException $e) {
      throw new SearchApiTypesenseException(
        $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  /**
   * Gets a Typesense collection.
   *
   * @param string|null $collection_name
   *   The name of the collection to retrieve.
   * @param bool $throw
   *   Whether to throw an exception if the collection is not found.
   *
   * @return \Typesense\Collection|null
   *   The collection, or NULL if none was found.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   *
   * @see https://typesense.org/docs/latest/api/collections.html#retrieve-a-collection
   */
  private function retrieveCollection(
    ?string $collection_name,
    bool $throw = TRUE,
  ): ?Collection {
    try {
      $collection = $this->client->collections[$collection_name];
      // Ensure that collection exists on the typesense server by retrieving it.
      // This throws exception if it is not found.
      $collection->retrieve();

      return $collection;
    }
    catch (Exception | TypesenseClientError $e) {
      if ($throw) {
        throw new SearchApiTypesenseException(
          $e->getMessage(),
          $e->getCode(),
          $e,
        );
      }

      return NULL;
    }
  }

}
