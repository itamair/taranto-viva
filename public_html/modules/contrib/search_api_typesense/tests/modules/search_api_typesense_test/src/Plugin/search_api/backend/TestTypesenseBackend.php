<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense_test\Plugin\search_api\backend;

use Drupal\search_api\IndexInterface;
use Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend;

/**
 * Provides a test backend for Search API Typesense.
 *
 * @SearchApiBackend(
 *   id = "search_api_typesense_test",
 *   label = @Translation("Search API Typesense Test"),
 *   description = @Translation("Test backend for Search API Typesense.")
 * )
 */
class TestTypesenseBackend extends SearchApiTypesenseBackend {

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionSpecificSearchParameters(IndexInterface $index): array {
    return [
      'query_by' => '',
      'query_by_weights' => '',
      'sort_by' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionRenderParameters(IndexInterface $index): array {
    return [
      'collection_name' => 'test_index',
      'collection_label' => 'Test Index',
      'all_fields' => [],
      'entity_types' => ['node'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFacetsForCollection(IndexInterface $index): array {
    return [];
  }

}
