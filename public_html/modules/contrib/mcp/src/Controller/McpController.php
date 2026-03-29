<?php

declare(strict_types=1);

namespace Drupal\mcp\Controller;

use Drupal\Core\Utility\Error;
use Drupal\jsonrpc\Exception\JsonRpcException;
use Drupal\jsonrpc\Object\Response as RpcResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\jsonrpc\Controller\HttpController;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Model Context Protocol routes.
 */
class McpController extends HttpController {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->handler = $container->get('mcp.jsonrpc.handler');

    return $instance;
  }

  /**
   * Post endpoint for receiving messages from the client.
   */
  public function post(Request $request) {
    try {
      return $this->resolve($request);
    }
    catch (\Exception $e) {
      Error::logException(
        $this->getLogger('mcp'),
        $e,
        'An error occurred while handling the response: @message',
        [
          '@message' => $e->getMessage(),
        ]
      );

      return $this->exceptionResponse(
        JsonRpcException::fromPrevious($e)
      );
    }
  }

  /**
   * Intersects all the headers in the RPC responses into a single bag.
   *
   * @param \Drupal\jsonrpc\Object\Response[]|null $rpc_responses
   *   The RPC responses. Can be null or empty array.
   *
   * @return \Symfony\Component\HttpFoundation\HeaderBag
   *   The aggregated header bag or empty if no responses.
   */
  public function aggregateResponseHeaders(?array $rpc_responses): HeaderBag {
    if (empty($rpc_responses)) {
      return new HeaderBag();
    }

    return array_reduce($rpc_responses, function (?HeaderBag $carry, RpcResponse $response) {
      $response_headers = $response->getHeaders();

      $intersected_headers = $carry ? array_intersect_key(
        $carry->all(),
        $response_headers->all()
      ) : $response_headers->all();

      return new HeaderBag($intersected_headers);
    });
  }

}
