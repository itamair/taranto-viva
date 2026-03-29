<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Api;

use Drupal\search_api_typesense\Enum\FacetFieldType;
use Typesense\Collection;

/**
 * Interface for the Search Api Typesense client.
 */
interface TypesenseClientInterface {

  /**
   * Searches specified collection for given string.
   *
   * @param string $collection_name
   *   The name of the collection to search.
   * @param TypesenseSearchParameters $parameters
   *   The array of query parameters.
   *
   * @return TypesenseDocument[]
   *   The results array.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   *
   * @see https://typesense.org/docs/latest/api/documents.html#search
   */
  public function searchDocuments(
    string $collection_name,
    array $parameters,
  ): array;

  /**
   * Performs a multi\semantic\hybrid search on the specified collection.
   *
   * @param array $searches
   *   An array of searches to perform.
   *   [
   *    [
   *      'query_by' => 'field_1,field_2,embedding',
   *      'q' => 'embedding',
   *      'collection' => 'collection_name',
   *      'sort_by' => '_text_match:desc',
   *    ]
   *   ]
   *   'query_by', 'q' and 'collection are required.
   *   Add 'embedding' to 'query_by' for semantic search.
   *   'sort_by' is optional.
   * @param array $query_params
   *   Additional query parameters, such as vector_query:
   *   [
   *    'vector_query' => 'embedding:([], k: 200, distance_threshold: 0.5)',
   *   ].
   * @param bool $union
   *   Whether perform a union search.
   *
   * @return array<array-key, array<array-key, mixed>>
   *   The result array.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   *
   * @see https://typesense.org/docs/0.24.0/api/federated-multi-search.html
   * @see https://typesense.org/docs/guide/semantic-search.html
   */
  public function multiSearch(
    array $searches,
    array $query_params = [],
    bool $union = FALSE,
  ): array;

  /**
   * Creates a Typesense collection.
   *
   * @param array $schema
   *   A typesense schema.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   *
   * @see https://typesense.org/docs/latest/api/collections.html#create-a-collection
   */
  public function createCollection(array $schema): Collection;

  /**
   * Removes a Typesense index.
   *
   * @param string|null $collection_name
   *   The name of the index to remove.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   *
   * @see https://typesense.org/docs/latest/api/collections.html#drop-a-collection
   */
  public function dropCollection(?string $collection_name): void;

  /**
   * Lists all collections.
   *
   * @phpstan-return TypesenseCollection
   *   The set of collections for the server.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   *
   * @see https://typesense.org/docs/latest/api/collections.html#list-all-collections
   */
  public function retrieveCollections(): array;

  /**
   * Adds a document to a collection, or updates an existing document.
   *
   * @param string $collection_name
   *   The collection to create the new document on.
   * @param array $document
   *   The document to create.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   *
   * @see https://typesense.org/docs/latest/api/documents.html#upsert
   *
   * @see https://typesense.org/docs/latest/api/documents.html#index-a-document
   */
  public function createDocument(
    string $collection_name,
    array $document,
  ): void;

  /**
   * Retrieves a specific indexed document.
   *
   * @param string $collection_name
   *   The name of the collection to query for the document.
   * @param string $id
   *   The id of the document to retrieve.
   *
   * @return array|null
   *   The retrieved document.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   *
   * @see https://typesense.org/docs/latest/api/documents.html#retrieve-a-document
   */
  public function retrieveDocument(
    string $collection_name,
    string $id,
  ): array | null;

  /**
   * Deletes a specific indexed document.
   *
   * @param string $collection_name
   *   The name of the collection containing the document.
   * @param string $id
   *   The id of the document to delete.
   *
   * @return array
   *   The deleted document.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   *
   * @see https://typesense.org/docs/latest/api/documents.html#delete-documents
   */
  public function deleteDocument(string $collection_name, string $id): array;

  /**
   * Deletes all documents satisfying a certain condition.
   *
   * @param string $collection_name
   *   The name of the collection containing the documents.
   * @param array $filter_condition
   *   The condition specifying which documents to delete.
   *
   * @return array
   *   An array containing the quantity of documents deleted.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   *
   * @see https://typesense.org/docs/latest/api/documents.html#delete-by-query
   */
  public function deleteDocuments(
    string $collection_name,
    array $filter_condition,
  ): array;

  /**
   * Adds a synonym to a collection, or updates an existing synonym.
   *
   * @param string $collection_name
   *   The collection to create the new synonym on.
   * @param string $id
   *   The id of the synonym to create.
   * @param array $synonym
   *   The synonym to create.
   *
   * @return array
   *   The newly added/updated synonym.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   *
   * @see https://typesense.org/docs/latest/api/synonyms.html#multi-way-synonym
   * @see https://typesense.org/docs/latest/api/synonyms.html#arguments
   *
   * @see https://typesense.org/docs/latest/api/synonyms.html#create-or-update-a-synonym
   */
  public function createSynonym(
    string $collection_name,
    string $id,
    array $synonym,
  ): array;

  /**
   * Retrieves a specific synonym.
   *
   * @param string $collection_name
   *   The name of the collection to query for the synonym.
   * @param string $id
   *   The id of the synonym to retrieve.
   *
   * @return array
   *   The retrieved synonym.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   *
   * @see https://typesense.org/docs/latest/api/synonyms.html#retrieve-a-synonym
   */
  public function retrieveSynonym(string $collection_name, string $id): array;

  /**
   * Retrieves all synonyms.
   *
   * @param string $collection_name
   *   The name of the collection to query for the synonym.
   *
   * @return array
   *   The retrieved synonyms.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   *
   * @see https://typesense.org/docs/latest/api/synonyms.html#list-all-synonyms
   */
  public function retrieveSynonyms(string $collection_name): array;

  /**
   * Deletes a specific synonym.
   *
   * @param string $collection_name
   *   The name of the collection containing the synonym.
   * @param string $id
   *   The id of the synonym to delete.
   *
   * @return array
   *   The deleted synonym.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   *
   * @see https://typesense.org/docs/latest/api/synonyms.html#delete-a-synonym
   */
  public function deleteSynonym(string $collection_name, string $id): array;

  /**
   * Adds a curation to a collection, or updates an existing curation.
   *
   * @param string $collection_name
   *   The collection to create the new synonym on.
   * @param string $id
   *   The id of the synonym to create.
   * @param array $curation
   *   The curation to create.
   *
   * @return array
   *   The newly added/updated curation.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   *
   * @see https://typesense.org/docs/latest/api/curation.html#create-or-update-an-override
   */
  public function createCuration(
    string $collection_name,
    string $id,
    array $curation,
  ): array;

  /**
   * Retrieves a specific curation.
   *
   * @param string $collection_name
   *   The name of the collection to query for the curation.
   * @param string $id
   *   The id of the curation to retrieve.
   *
   * @return array
   *   The retrieved curation.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   *
   * @see https://typesense.org/docs/latest/api/curation.html#retrieve-an-override
   */
  public function retrieveCuration(string $collection_name, string $id): array;

  /**
   * Retrieves all curations.
   *
   * @param string $collection_name
   *   The name of the collection to query for the curation.
   *
   * @return array
   *   The retrieved curations.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   *
   * @see https://typesense.org/docs/latest/api/curation.html#list-all-overrides
   */
  public function retrieveCurations(string $collection_name): array;

  /**
   * Deletes a specific curation.
   *
   * @param string $collection_name
   *   The name of the collection containing the curation.
   * @param string $id
   *   The id of the curation to delete.
   *
   * @return array
   *   The deleted curation.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   *
   * @see https://typesense.org/docs/latest/api/curation.html#delete-an-override
   */
  public function deleteCuration(string $collection_name, string $id): array;

  /**
   * Adds a conversation model to a collection, or updates an existing one.
   *
   * @param array $params
   *   The conversation model to create.
   *
   * @return array
   *   The newly added/updated conversation model.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   *
   * @see https://typesense.org/docs/latest/api/synonyms.html#create-a-conversation-model
   */
  public function createConversationModel(array $params): array;

  /**
   * Retrieves a specific conversation model.
   *
   *   The id of the conversation model to retrieve.
   *
   * @return array
   *   The retrieved conversation model.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   *
   * @see https://typesense.org/docs/latest/api/synonyms.html#retrieve-a-single-model
   */
  public function retrieveConversationModel(string $id): array;

  /**
   * Retrieves all conversation models.
   *
   * @return array
   *   The retrieved conversation models.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   *
   * @see https://typesense.org/docs/latest/api/synonyms.html#retrieve-all-models
   */
  public function retrieveConversationModels(): array;

  /**
   * Updates a specific conversation model.
   *
   * @param string $id
   *   The id of the synonym to update.
   * @param array $params
   *   The conversation model to update.
   *
   * @return array
   *   The updated conversation model.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   *
   * @see https://typesense.org/docs/latest/api/synonyms.html#update-a-model
   */
  public function updateConversationModel(string $id, array $params): array;

  /**
   * Deletes a specific conversation model.
   *
   * @param string $id
   *   The id of the synonym to delete.
   *
   * @return array
   *   The deleted synonym.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   *
   * @see https://typesense.org/docs/latest/api/synonyms.html#delete-a-model
   */
  public function deleteConversationModel(string $id): array;

  /**
   * Checks if the conversation history collection exists.
   *
   * @return bool
   *   TRUE if the conversation history collection exists, FALSE otherwise.
   */
  public function hasConversationHistoryCollection(): bool;

  /**
   * Ensures the conversation history collection exists.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   */
  public function ensureConversationHistoryCollection(): void;

  /**
   * Returns collection information.
   *
   * @param string $collection_name
   *   The name of the collection to retrieve information for.
   *
   * @return array|null
   *   An associative array containing the collection's information. Or NULL if
   *   the collection does not exist.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   */
  public function retrieveCollectionInfo(string $collection_name): array | null;

  /**
   * Returns the health of the Typesense server.
   *
   * @return array
   *   An array containing a boolean 'ok': [ 'ok' => TRUE ].
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   */
  public function retrieveHealth(): array;

  /**
   * Returns the debug info from the Typesense server.
   *
   * @return array
   *   An associative array containing two keys, 'state', and 'version'.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   */
  public function retrieveDebug(): array;

  /**
   * Returns the metrics info from the Typesense server.
   *
   * @return array
   *   An associative array containing the server's metrics.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   */
  public function retrieveMetrics(): array;

  /**
   * Creates a key.
   *
   * @param array $schema
   *   A typesense schema for API Key.
   *
   * @return array
   *   The created key response.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   *
   * @see https://typesense.org/docs/latest/api/api-keys.html#create-an-api-key
   */
  public function createKey(array $schema): array;

  /**
   * Retrieves a key.
   *
   * @param int $key_id
   *   The id of the key to retrieve.
   *
   * @return array
   *   The retrieved key.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   *
   * @see https://typesense.org/docs/latest/api/api-keys.html#retrieve-an-api-key
   */
  public function retrieveKey(int $key_id): array;

  /**
   * Returns current server keys.
   *
   * @return array
   *   The array of the server's keys.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   */
  public function retrieveKeys(): array;

  /**
   * Deletes a key.
   *
   * @param int $key_id
   *   The id of the key to delete.
   *
   * @return array
   *   The deleted key.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   *
   * @see https://typesense.org/docs/latest/api/api-keys.html#delete-api-key
   */
  public function deleteKey(int $key_id): array;

  /**
   * Creates a stopword.
   *
   * @param array $schema
   *   A typesense schema for API Key.
   *
   * @return array
   *   The created key response.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   *
   * @see https://typesense.org/docs/latest/api/stopwords.html#adding-stopwords
   */
  public function createStopword(array $schema): array;

  /**
   * Retrieves a stopword.
   *
   * @param string $name
   *   The name of the stopword to retrieve.
   *
   * @return array
   *   The retrieved key.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   *
   * @see https://typesense.org/docs/latest/api/stopwords.html#get-a-specific-stopwords-set
   */
  public function retrieveStopword(string $name): array;

  /**
   * Returns all server stopwords.
   *
   * @return array
   *   The array of the server's stopwords.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   *
   * @see https://typesense.org/docs/latest/api/stopwords.html#getting-all-stopwords-sets
   */
  public function retrieveStopwords(): array;

  /**
   * Deletes a stopwords.
   *
   * @param string $name
   *   The name of the stopwords to delete.
   *
   * @return array
   *   The deleted stopword.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   */
  public function deleteStopword(string $name): array;

  /**
   * Import the collection data.
   *
   * @param string $collection_name
   *   The name of the collection to import to.
   * @param array $data
   *   The collection data.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   */
  public function importCollectionData(
    string $collection_name,
    array $data,
  ): void;

  /**
   * Export the collection data.
   *
   * @param string $collection_name
   *   The name of the collection to export.
   * @param bool $include_schema
   *   Whether to include the collection schema in the export.
   *
   * @return array
   *   The collection data.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException
   */
  public function exportCollectionData(
    string $collection_name,
    bool $include_schema = FALSE,
  ): array;

  /**
   * Prepares the id for typesense-indexing.
   *
   * @param string $id
   *   The incoming entity id from Drupal.
   *
   * @return string
   *   The prepared id, ready for Typesense indexing.
   */
  public function prepareId(string $id): string;

  /**
   * Prepares items for typesense-indexing.
   *
   * @param array $value
   *   The incoming entity value from Drupal.
   * @param string $type
   *   The specified data type from the Search API index configuration.
   * @param string $field_name
   *   The name of the field in the Search API index configuration.
   *
   * @return bool|float|int|string|array
   *   The prepared item, ready for Typesense indexing.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   */
  public function prepareItemValue(
    array $value,
    string $type,
    string $field_name,
  ): bool | float | int | string | array;

  /**
   * Retrieves the fields for a collection.
   *
   * @param string $collection_name
   *   The name of the collection to retrieve fields for.
   *
   * @return string[]
   *   The fields for the collection.
   */
  public function getFields(string $collection_name): array;

  /**
   * Retrieves the fields for a collection that can be queried by.
   *
   * @param string $collection_name
   *   The name of the collection to retrieve fields for.
   *
   * @return string[]
   *   The fields for the collection that can be queried by.
   */
  public function getFieldsForQueryBy(string $collection_name): array;

  /**
   * Retrieves the fields for a collection that can be used for facets.
   *
   * @param string $collection_name
   *   The name of the collection to retrieve fields for.
   * @param \Drupal\search_api_typesense\Enum\FacetFieldType $field_type
   *   The type of facet field to retrieve.
   *
   * @return string[]
   *   The fields for the collection that can be used for facets.
   */
  public function getFieldsForFacetType(string $collection_name, FacetFieldType $field_type): array;

  /**
   * Retrieves the fields for a collection that can be used for sorting by.
   *
   * @param array $fields
   *   The fields for the collection.
   *
   * @return array
   *   The fields for the collection that can be used for sorting by.
   */
  public function getFieldsForSortBy(array $fields): array;

  /**
   * Retrieves the weight for a collection that can be used for query by weight.
   *
   * @param array $fields
   *   The fields for the collection.
   *
   * @return array
   *   The weights for the collection that can be used for query by weight.
   */
  public function getQueryByWeight(array $fields): array;

  /**
   * Generates a scoped search key.
   *
   * @param string $key
   *   The search key.
   * @param array $parameters
   *   The array of query parameters.
   *
   * @return string
   *   The scoped search key.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   */
  public function generateScopedSearchKey(
    string $key,
    array $parameters,
  ): string;

}
