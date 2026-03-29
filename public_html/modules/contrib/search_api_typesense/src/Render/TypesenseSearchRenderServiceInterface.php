<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Render;

use Drupal\search_api\ServerInterface;
use Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend;

/**
 * Interface for the Typesense search render service.
 */
interface TypesenseSearchRenderServiceInterface {

  /**
   * Builds a search render array from the given context.
   *
   * @param \Drupal\search_api_typesense\Render\TypesenseSearchRenderContext $context
   *   The render context containing all necessary data.
   *
   * @return array<string, mixed>
   *   A renderable array for the search interface.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   *   If there's an error preparing the search data.
   */
  public function buildSearchRender(TypesenseSearchRenderContext $context): array;

  /**
   * Validates and returns a Typesense backend from a server.
   *
   * @param \Drupal\search_api\ServerInterface $server
   *   The Search API server.
   *
   * @return \Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend
   *   The validated Typesense backend.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   *   If the server doesn't use a Typesense backend.
   */
  public function validateBackend(ServerInterface $server): SearchApiTypesenseBackend;

  /**
   * Prepares collection data for multiple indexes.
   *
   * This method extracts collection-specific search parameters, render
   * parameters, and facets from the given indexes using the backend.
   *
   * @param \Drupal\search_api\IndexInterface[] $indexes
   *   Array of Search API indexes.
   * @param \Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend $backend
   *   The Typesense backend instance.
   *
   * @return array{
   *   collection_specific_search_parameters: array<string,
   *     array<string, mixed>>,
   *   collection_render_parameters: list<array<string, mixed>>,
   *   facets: list<array<string, mixed>>
   *   }
   *   Associative array containing prepared collection data.
   *
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   *   If there's an error retrieving collection data.
   */
  public function prepareCollectionData(array $indexes, SearchApiTypesenseBackend $backend): array;

}
