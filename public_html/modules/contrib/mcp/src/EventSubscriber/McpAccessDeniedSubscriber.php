<?php

declare(strict_types=1);

namespace Drupal\mcp\EventSubscriber;

use Drupal\mcp\Exception\McpAuthException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * MCP Access Denied Event Subscriber.
 */
final class McpAccessDeniedSubscriber implements EventSubscriberInterface {

  /**
   * Event handler for exceptions.
   */
  public function onException(ExceptionEvent $event) {
    $exception = $event->getThrowable();
    $request = $event->getRequest();

    if ($exception instanceof AccessDeniedHttpException
      && str_starts_with($request->getRequestUri(), '/mcp')
    ) {
      $event->setResponse(new JsonResponse([
        'jsonrpc' => '2.0',
        'error'   => [
          'code'    => -32001,
          'message' => $exception->getPrevious() instanceof McpAuthException
            ? $exception->getPrevious()->getMessage() : 'Access Denied',
          'data'    => NULL,
        ],
        'id'      => NULL,
      ], Response::HTTP_UNAUTHORIZED));
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::EXCEPTION => ['onException', 10],
    ];
  }

}
