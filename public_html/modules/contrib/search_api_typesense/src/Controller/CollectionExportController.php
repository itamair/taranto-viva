<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\Url;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api_typesense\Api\SearchApiTypesenseException;
use Drupal\search_api_typesense\Api\SearchApiTypesenseRequestUnauthorizedException;
use Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a controller for exporting a Typesense collection.
 */
class CollectionExportController extends ControllerBase {

  /**
   * CollectionExportController constructor.
   *
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManager $streamWrapperManager
   *   The stream wrapper manager.
   */
  public function __construct(
    private readonly StreamWrapperManager $streamWrapperManager,
  ) {}

  /**
   * Exports a Typesense collection.
   *
   * @param \Drupal\search_api\IndexInterface $search_api_index
   *   The search index.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\RedirectResponse
   *   The response.
   */
  public function __invoke(
    IndexInterface $search_api_index,
  ): BinaryFileResponse | RedirectResponse {
    try {
      $search_api_server = $search_api_index->getServerInstance();
      $backend = $search_api_server?->getBackend();
      if (!$backend instanceof SearchApiTypesenseBackend) {
        throw new SearchApiTypesenseException('The server must use the Typesense backend.');
      }

      if (!$backend->isAvailable()) {
        throw new NotFoundHttpException($this->t('The Typesense server is not available.')
          ->render());
      }

      $collection_name = $backend->getCollectionName($search_api_index);
      $collection_data = $backend
        ->getTypesenseClient()
        ->exportCollectionData(
          collection_name: $collection_name,
          include_schema : TRUE,
        );

      $filename = $search_api_server?->id() . '_' . $search_api_index->id() . '_collection.json';
      $schema = 'temporary://';

      \file_put_contents(
        $schema . $filename,
        \json_encode($collection_data, JSON_PRETTY_PRINT),
      );

      $uri = $this->streamWrapperManager->normalizeUri($schema . $filename);

      if (\is_file($uri)) {
        $response = new BinaryFileResponse($uri);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT);

        return $response;
      }
    }
    catch (SearchApiTypesenseRequestUnauthorizedException $e) {
      $this->messenger()->addError($e->getMessage());

      return new RedirectResponse(
        Url::fromRoute(
          'search_api_typesense.collection.export',
          ['search_api_index' => $search_api_index->id()],
        )->toString(),
      );
    }
    catch (SearchApiTypesenseException | SearchApiException $e) {
      throw new HttpException(500,
        $this->t('An error occurred while exporting the index: @message',
          [
            '@message' => $e->getMessage(),
          ])->render());
    }

    throw new NotFoundHttpException();
  }

}
