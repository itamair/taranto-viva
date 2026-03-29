<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Render;

use Drupal\search_api\ServerInterface;
use Drupal\search_api_typesense\Api\SearchApiTypesenseException;
use Drupal\search_api_typesense\Event\SearchRenderEvent;
use Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Service for rendering Typesense search interfaces.
 *
 * This service provides shared functionality for rendering search interfaces
 * across different contexts (blocks, controllers, etc.) to eliminate code
 * duplication and ensure consistency.
 */
class TypesenseSearchRenderService implements TypesenseSearchRenderServiceInterface {

  /**
   * Constructs a TypesenseSearchRenderService object.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher service.
   */
  public function __construct(
    private readonly EventDispatcherInterface $eventDispatcher,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function buildSearchRender(TypesenseSearchRenderContext $context): array {
    $backend = $context->getBackend();
    $indexes = $context->getIndexes();

    // Prepare collection data.
    $collection_data = $this->prepareCollectionData($indexes, $backend);

    // Get backend configuration.
    $configuration = $backend->getConfiguration();

    // Build base JavaScript settings.
    $js_settings = [
      'server' => [
        'apiKey' => $context->getApiKey(),
        'nodes' => $this->retrievePublicNodes($configuration),
      ],
      'collection_specific_search_parameters' => $collection_data['collection_specific_search_parameters'],
      'collection_render_parameters' => $collection_data['collection_render_parameters'],
      'hits_per_page' => $context->getHitsPerPage(),
      'facets' => $collection_data['facets'],
    ];

    // Add current language if provided.
    if ($context->getCurrentLanguage() !== NULL) {
      $js_settings['current_langcode'] = $context->getCurrentLanguage();
    }

    // Add debug mode if enabled.
    if ($context->isDebugEnabled()) {
      $js_settings['debug'] = TRUE;
    }

    // Merge in any additional settings.
    $js_settings = \array_merge($js_settings, $context->getAdditionalSettings());

    $render_array = [
      'content' => [
        '#theme' => 'search_api_typesense_search',
        '#collection_render_parameters' => $collection_data['collection_render_parameters'],
        '#facets' => $collection_data['facets'],
        '#block_uuid' => $context->getBlockUuid(),
      ],
      '#attached' => [
        'drupalSettings' => [
          'search_api_typesense' => [
            'blocks' => [
              $context->getBlockUuid() => $js_settings,
            ],
          ],
        ],
      ],
    ];

    // Dispatch event to allow other modules to alter the render array.
    $event = new SearchRenderEvent($render_array, $context);
    $this->eventDispatcher->dispatch($event, SearchRenderEvent::NAME);

    return $event->getRenderArray();
  }

  /**
   * {@inheritdoc}
   */
  public function validateBackend(ServerInterface $server): SearchApiTypesenseBackend {
    $backend = $server->getBackend();
    if (!$backend instanceof SearchApiTypesenseBackend) {
      throw new SearchApiTypesenseException('The server must use the Typesense backend.');
    }

    return $backend;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareCollectionData(array $indexes, SearchApiTypesenseBackend $backend): array {
    $collection_specific_search_parameters = [];
    $collection_render_parameters = [];
    $facets = [];

    foreach ($indexes as $search_api_index) {
      $collection_name = $backend->getCollectionName($search_api_index);
      $collection_specific_search_parameters[$collection_name] = $backend->getCollectionSpecificSearchParameters($search_api_index);
      $collection_render_parameters[] = $backend->getCollectionRenderParameters($search_api_index);
      $facets = \array_merge($facets, $backend->getFacetsForCollection($search_api_index));
    }

    return [
      'collection_specific_search_parameters' => $collection_specific_search_parameters,
      'collection_render_parameters' => $collection_render_parameters,
      'facets' => $facets,
    ];
  }

  /**
   * Retrieves the public nodes configuration.
   *
   * If a public endpoint is configured, it returns that as the sole node.
   * Otherwise, it returns the list of nodes from the configuration.
   *
   * @param array $configuration
   *   The backend configuration array.
   *
   * @return array
   *   An array of nodes with 'host', 'port', and 'protocol' keys.
   */
  private function retrievePublicNodes(array $configuration): array {
    if (isset($configuration['public_endpoint']['host']) && isset($configuration['public_endpoint']['port']) && isset($configuration['public_endpoint']['protocol'])) {
      return [
        [
          'host' => $configuration['public_endpoint']['host'],
          'port' => $configuration['public_endpoint']['port'],
          'protocol' => $configuration['public_endpoint']['protocol'],
        ],
      ];
    }

    return $configuration['nodes'];
  }

}
