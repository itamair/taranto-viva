<?php

declare(strict_types=1);

namespace Drupal\jsonrpc\Exception;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Helper methods for translating errors.
 *
 * Significant portions of this file are used with credit from Monolog (MIT
 * license.)
 */
final class ErrorHandler {

  /**
   * Logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs an ErrorHandler object.
   *
   * @todo Provide a mechanism for registering additional log level mappings.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger channel.
   */
  public function __construct(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * Retrieve a mapping of log levels for uncaught exceptions.
   *
   * @return array<class-string, LogLevel::*>
   *   Mapping of Exception classes to PSR log levels.
   */
  protected static function defaultExceptionLevelMap(): array {
    return [
      'ParseError' => LogLevel::CRITICAL,
      'Throwable' => LogLevel::ERROR,
    ];
  }

  /**
   * Get the log level for an exception.
   *
   * @param \Throwable $e
   *   The exception/throwable.
   *
   * @return string
   *   The log level.
   */
  protected function getLogLevelForException(\Throwable $e) {
    $level = LogLevel::ERROR;
    foreach (self::defaultExceptionLevelMap() as $class => $candidate) {
      if ($e instanceof $class) {
        $level = $candidate;
        break;
      }
    }
    return $level;
  }

  /**
   * Log a server exception.
   *
   * @param \Drupal\jsonrpc\Exception\JsonRpcException $e
   *   Thrown exception.
   */
  public function logServerError(JsonRpcException $e): void {
    $ex = $e->getPrevious() ?? $e;
    $this->logger->log(
      $this->getLogLevelForException($ex),
      sprintf('Exception %s: "%s" at %s line %s', self::getClass($ex), $ex->getMessage(), $ex->getFile(), $ex->getLine()),
      ['exception' => $e]
    );
  }

  /**
   * Utility to get a class name for an object suitable for display.
   *
   * @param object $object
   *   Subject object.
   *
   * @return string
   *   Display class name.
   */
  public static function getClass(object $object): string {
    $class = \get_class($object);

    if (FALSE === ($pos = \strpos($class, "@anonymous\0"))) {
      return $class;
    }

    if (FALSE === ($parent = \get_parent_class($class))) {
      return \substr($class, 0, $pos + 10);
    }

    return $parent . '@anonymous';
  }

}
