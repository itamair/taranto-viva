<?php

declare(strict_types=1);

namespace Drupal\Tests\monolog\Unit\Logger;

use Drupal\Core\Logger\LogMessageParser;
use Drupal\monolog\Logger\Processor\MessagePlaceholderProcessor;
use Drupal\Tests\UnitTestCase;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * @coversDefaultClass \Drupal\monolog\Logger\Processor\MessagePlaceholderProcessor
 * @group monolog
 */
class MessagePlaceholderProcessorTest extends UnitTestCase {

  /**
   * Make shure that the message's placeholders are replaced.
   *
   * @dataProvider providerTestMessagePlaceholders
   */
  public function testMessagePlaceholders($message, array $context, $expected): void {
    $parser = new LogMessageParser();
    $processor = new MessagePlaceholderProcessor($parser);
    $record = new LogRecord(
      datetime: new \DateTimeImmutable(),
      channel: 'test',
      level: Level::Info,
      message: $message,
      context: $context,
      extra: [],
    );
    $record = $processor($record);
    $this->assertEquals($expected, $record->message);
  }

  /**
   * Data provider for self::testMessagePlaceholders().
   */
  public static function providerTestMessagePlaceholders(): array {
    return [
      ['User @name created', ['@name' => 'admin'], 'User admin created'],
      ['User {name} created', ['name' => 'admin'], 'User admin created'],
      ['User {name} with email {email} created', ['name' => 'admin', 'email' => 'admin@example.com'], 'User admin with email admin@example.com created'],
      ['User {name} with email @email created', ['name' => 'admin', '@email' => 'admin@example.com'], 'User admin with email admin@example.com created'],
      ['User {name} with email {email} created', ['name' => 'admin'], 'User admin with email @email created'],
      ['User {name} with email {email} created', [], 'User @name with email @email created'],
      ['User @name created', [], 'User @name created'],
      ['User name created', ['name' => 'admin'], 'User name created'],
    ];
  }

}
