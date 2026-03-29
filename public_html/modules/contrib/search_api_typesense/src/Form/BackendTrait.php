<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Form;

use Drupal\search_api\IndexInterface;
use Drupal\search_api_typesense\Api\SearchApiTypesenseException;
use Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend;

/**
 * Provides a trait for backend related methods.
 */
trait BackendTrait {

  /**
   * Return the Typesense backend.
   *
   * @param \Drupal\search_api\IndexInterface|null $search_api_index
   *   The search API index.
   *
   * @return \Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend
   *   The Typesense backend.
   *
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   */
  private function getBackend(
    ?IndexInterface $search_api_index,
  ): SearchApiTypesenseBackend {
    if ($search_api_index === NULL) {
      $this->messenger()->addError(
        $this->t('The search API index is not available.'),
      );

      throw new SearchApiTypesenseException('The search API index is not available.');
    }

    $search_api_server = $search_api_index->getServerInstance();
    $backend = $search_api_server?->getBackend();
    if (!$backend instanceof SearchApiTypesenseBackend) {
      throw new SearchApiTypesenseException('The server must use the Typesense backend.');
    }

    if (!$backend->isAvailable()) {
      throw new SearchApiTypesenseException('The Typesense server is not available.');
    }

    $collection_name = $backend->getCollectionName($search_api_index);
    $collection = $backend->getTypesenseClient()
      ->retrieveCollectionInfo($collection_name);
    if ($collection === NULL) {
      throw new SearchApiTypesenseException('The collection does not exist.');
    }

    return $backend;
  }

  /**
   * Return the Typesense backend from the search API index ID.
   *
   * @param int|string $search_api_index_id
   *   The search API index ID.
   *
   * @return \Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend
   *   The Typesense backend.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   */
  private function getBackendFromIndexId(
    int|string $search_api_index_id,
  ): SearchApiTypesenseBackend {
    $search_api_index = \Drupal::entityTypeManager()->getStorage('search_api_index')->load($search_api_index_id);

    return $this->getBackend($search_api_index);
  }

  /**
   * Return the Typesense backend from the search API server ID.
   *
   * @param int|string $search_api_server_id
   *   The search API server ID.
   *
   * @return \Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend
   *   The Typesense backend.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\search_api\SearchApiException
   * @throws \Drupal\search_api_typesense\Api\SearchApiTypesenseException
   */
  private function getBackendFromServerId(
    int|string $search_api_server_id,
  ): SearchApiTypesenseBackend {
    /** @var \Drupal\search_api\Entity\Server $search_api_server */
    $search_api_server = \Drupal::entityTypeManager()->getStorage('search_api_server')->load($search_api_server_id);

    $backend = $search_api_server->getBackend();
    if (!$backend instanceof SearchApiTypesenseBackend) {
      throw new SearchApiTypesenseException('The server must use the Typesense backend.');
    }

    if (!$backend->isAvailable()) {
      throw new SearchApiTypesenseException('The Typesense server is not available.');
    }

    return $backend;
  }

}
