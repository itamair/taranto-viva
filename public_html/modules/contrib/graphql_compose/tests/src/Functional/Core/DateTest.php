<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose\Functional\Core;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\graphql_compose\Functional\GraphQLComposeBrowserTestBase;
use Drupal\node\NodeInterface;

/**
 * Test date generation and formatting.
 *
 * @group graphql_compose
 */
class DateTest extends GraphQLComposeBrowserTestBase {

  /**
   * The test node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $node;

  /**
   * The test date.
   *
   * @var int
   */
  protected int $now;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'datetime',
    'datetime_range',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createContentType([
      'type' => 'test',
      'name' => 'Test node type',
    ]);

    // Create a datetime field, date only.
    FieldStorageConfig::create([
      'field_name' => 'field_date_only',
      'type' => 'datetime',
      'entity_type' => 'node',
      'settings' => [
        'datetime_type' => 'date',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_date_only',
      'entity_type' => 'node',
      'bundle' => 'test',
      'label' => 'Date only',
      'required' => FALSE,
    ])->save();

    // Create a date and time field.
    FieldStorageConfig::create([
      'field_name' => 'field_date_and_time',
      'type' => 'datetime',
      'entity_type' => 'node',
      'settings' => [
        'datetime_type' => 'datetime',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_date_and_time',
      'entity_type' => 'node',
      'bundle' => 'test',
      'label' => 'Date and time',
      'required' => FALSE,
    ])->save();

    // Create date range date only field.
    FieldStorageConfig::create([
      'field_name' => 'field_date_range_date_only',
      'type' => 'daterange',
      'entity_type' => 'node',
      'settings' => [
        'datetime_type' => 'date',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_date_range_date_only',
      'entity_type' => 'node',
      'bundle' => 'test',
      'label' => 'Date range date only',
      'required' => FALSE,
    ])->save();

    // Create date range date and time field.
    FieldStorageConfig::create([
      'field_name' => 'field_date_range_date_and_time',
      'type' => 'daterange',
      'entity_type' => 'node',
      'settings' => [
        'datetime_type' => 'datetime',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_date_range_date_and_time',
      'entity_type' => 'node',
      'bundle' => 'test',
      'label' => 'Date range date and time',
      'required' => FALSE,
    ])->save();

    // Create date range all day field.
    FieldStorageConfig::create([
      'field_name' => 'field_date_range_all_day',
      'type' => 'daterange',
      'entity_type' => 'node',
      'settings' => [
        'datetime_type' => 'allday',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_date_range_all_day',
      'entity_type' => 'node',
      'bundle' => 'test',
      'label' => 'Date range all day',
      'required' => FALSE,
    ])->save();

    $this->now = \Drupal::time()->getRequestTime();

    $this->node = $this->createNode([
      'type' => 'test',
      'title' => 'Test',
      'created' => $this->now,
      'changed' => $this->now,
      'field_date_only' => [
        'value' => '2025-05-25',
      ],
      'field_date_and_time' => [
        'value' => '2025-05-25 09:00:00',
      ],
      'field_date_range_date_only' => [
        'value' => '2025-05-25',
        'end_value' => '2025-05-26',
      ],
      'field_date_range_date_and_time' => [
        'value' => '2025-05-25T09:00:00',
        'end_value' => '2025-05-26T09:00:00',
      ],
      'field_date_range_all_day' => [
        'value' => '2025-05-25T00:00:00',
        'end_value' => '2025-05-26T00:00:00',
      ],
      'status' => 1,
    ]);

    $this->setEntityConfig('node', 'test', [
      'enabled' => TRUE,
      'query_load_enabled' => TRUE,
    ]);

    $this->setFieldConfig('node', 'test', 'field_date_only', [
      'enabled' => TRUE,
    ]);

    $this->setFieldConfig('node', 'test', 'field_date_and_time', [
      'enabled' => TRUE,
    ]);

    $this->setFieldConfig('node', 'test', 'field_date_range_date_only', [
      'enabled' => TRUE,
    ]);

    $this->setFieldConfig('node', 'test', 'field_date_range_date_and_time', [
      'enabled' => TRUE,
    ]);

    $this->setFieldConfig('node', 'test', 'field_date_range_all_day', [
      'enabled' => TRUE,
    ]);
  }

  /**
   * Test load entity by id.
   */
  public function testDateFields(): void {
    $query = <<<GQL
      query {
        node(id: "{$this->node->uuid()}") {
          ... on NodeInterface {
            created {
              time
              timezone
            }
            changed {
              time
              timezone
            }
          }
          ... on NodeTest {
            dateOnly {
              time
              timezone
            }
            dateAndTime {
              time
              timezone
            }
            dateRangeDateOnly {
              start {
                time
                timezone
              }
              end {
                time
                timezone
              }
            }
            dateRangeDateAndTime {
              start {
                time
                timezone
              }
              end {
                time
                timezone
              }
            }
            dateRangeAllDay {
              start {
                time
                timezone
              }
              end {
                time
                timezone
              }
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $node = $content['data']['node'];
    $this->assertNotNull($node['created'] ?? NULL);

    // Expected now stamp from string..
    $now = DrupalDateTime::createFromTimestamp($this->now, new \DateTimeZone('UTC'));
    $timezone = $now->getTimezone()->getName();

    // Created and changed are in the timezone of the server.
    $this->assertEquals($now->format(\DateTime::RFC3339), $node['created']['time']);
    $this->assertEquals($timezone, $node['created']['timezone']);

    // Stored dates and times are stored in UTC.
    $this->assertEquals('2025-05-25T12:00:00+00:00', $node['dateOnly']['time']);
    $this->assertEquals('UTC', $node['dateOnly']['timezone']);

    $this->assertEquals('2025-05-25T09:00:00+00:00', $node['dateAndTime']['time']);
    $this->assertEquals('UTC', $node['dateAndTime']['timezone']);

    // Date range date only.
    $this->assertEquals('2025-05-25T12:00:00+00:00', $node['dateRangeDateOnly']['start']['time']);
    $this->assertEquals('UTC', $node['dateRangeDateOnly']['start']['timezone']);

    $this->assertEquals('2025-05-26T12:00:00+00:00', $node['dateRangeDateOnly']['end']['time']);
    $this->assertEquals('UTC', $node['dateRangeDateOnly']['end']['timezone']);

    // Date range date and time.
    $this->assertEquals('2025-05-25T09:00:00+00:00', $node['dateRangeDateAndTime']['start']['time']);
    $this->assertEquals('UTC', $node['dateRangeDateAndTime']['start']['timezone']);

    $this->assertEquals('2025-05-26T09:00:00+00:00', $node['dateRangeDateAndTime']['end']['time']);
    $this->assertEquals('UTC', $node['dateRangeDateAndTime']['end']['timezone']);

    // Date range all day.
    $this->assertEquals('2025-05-25T00:00:00+00:00', $node['dateRangeAllDay']['start']['time']);
    $this->assertEquals('UTC', $node['dateRangeAllDay']['start']['timezone']);

    $this->assertEquals('2025-05-26T00:00:00+00:00', $node['dateRangeAllDay']['end']['time']);
    $this->assertEquals('UTC', $node['dateRangeAllDay']['end']['timezone']);

  }

}
