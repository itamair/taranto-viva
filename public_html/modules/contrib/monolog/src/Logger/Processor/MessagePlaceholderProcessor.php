<?php

declare(strict_types=1);

namespace Drupal\monolog\Logger\Processor;

use Drupal\Core\Logger\LogMessageParserInterface;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Parse and replace message placeholders.
 */
class MessagePlaceholderProcessor implements ProcessorInterface {

  /**
   * The message's placeholders parser.
   *
   * @var \Drupal\Core\Logger\LogMessageParserInterface
   */
  protected LogMessageParserInterface $parser;

  /**
   * Construct default object.
   *
   * @param \Drupal\Core\Logger\LogMessageParserInterface $parser
   *   The parser to use when extracting message variables.
   */
  public function __construct(LogMessageParserInterface $parser) {
    $this->parser = $parser;
  }

  /**
   * {@inheritdoc}
   */
  public function __invoke(LogRecord $record): LogRecord {
    // Extract the message placeholders from the message and context.
    $message_placeholders = $this
      ->parser
      ->parseMessagePlaceholders(
        $record['message'],
        $record['context'],
      );

    // Transform PSR3 style messages containing placeholders to
    // \Drupal\Component\Render\FormattableMarkup style.
    // @see Drupal\Core\Logger\LogMessageParser::parseMessagePlaceholders()
    $message = $record->message;
    if (($start = \strpos($message, '{')) !== FALSE && \strpos($message, '}') > $start) {
      $message = \preg_replace('/\{([^\{}]*)\}/U', '@$1', $message);
    }

    // Replace the placeholders in the message.
    $message = \count($message_placeholders) === 0
      ? $message
      : \strtr($message, $message_placeholders);

    // Remove the replaced placeholders from the context to prevent logging the
    // same information twice.
    $context = $record->context;
    foreach ($message_placeholders as $placeholder => $value) {
      unset($context[$placeholder]);
    }

    return $record->with(message: $message, context: $context);
  }

}
