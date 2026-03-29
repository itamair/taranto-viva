<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_mesh\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\entity_mesh\TrackerInterface;

/**
 * Tests the Entity Mesh Tracker service.
 *
 * @group entity_mesh
 * @coversDefaultClass \Drupal\entity_mesh\Tracker
 */
class TrackerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'language',
    'entity_mesh',
  ];

  /**
   * The tracker service under test.
   *
   * @var \Drupal\entity_mesh\TrackerInterface
   */
  protected $tracker;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install required schemas.
    $this->installSchema('entity_mesh', ['entity_mesh_tracker']);

    // Get services.
    $this->tracker = $this->container->get('entity_mesh.tracker');
    $this->database = $this->container->get('database');
  }

  /**
   * Tests adding an entity to the tracking table.
   *
   * @covers ::addEntity
   */
  public function testAddEntity(): void {
    // Add entity to tracking.
    $result = $this->tracker->addEntity('node', '123', TrackerInterface::OPERATION_PROCESS);

    $this->assertTrue($result, 'Entity should be added successfully');

    // Verify in database.
    $record = $this->database->select('entity_mesh_tracker', 't')
      ->fields('t')
      ->condition('entity_type', 'node')
      ->condition('entity_id', '123')
      ->execute()
      ->fetchAssoc();

    $this->assertNotEmpty($record, 'Record should exist in database');
    $this->assertEquals('node', $record['entity_type']);
    $this->assertEquals('123', $record['entity_id']);
    $this->assertEquals(TrackerInterface::OPERATION_PROCESS, $record['operation']);
    $this->assertEquals(TrackerInterface::STATUS_PENDING, $record['status']);
    $this->assertNotEmpty($record['timestamp'], 'Timestamp should be set');
  }

  /**
   * Tests adding same entity multiple times updates existing record.
   *
   * @covers ::addEntity
   */
  public function testAddEntityUpdatesExisting(): void {
    // Add entity first time.
    $this->tracker->addEntity('node', '123', TrackerInterface::OPERATION_PROCESS);

    // Add same entity again with different operation.
    $this->tracker->addEntity('node', '123', TrackerInterface::OPERATION_DELETE);

    // Should only have one record.
    $count = $this->database->select('entity_mesh_tracker', 't')
      ->condition('entity_type', 'node')
      ->condition('entity_id', '123')
      ->countQuery()
      ->execute()
      ->fetchField();

    $this->assertEquals(1, $count, 'Should only have one record for the entity');

    // Verify operation was updated.
    $record = $this->database->select('entity_mesh_tracker', 't')
      ->fields('t')
      ->condition('entity_type', 'node')
      ->condition('entity_id', '123')
      ->execute()
      ->fetchAssoc();

    $this->assertEquals(TrackerInterface::OPERATION_DELETE, $record['operation']);
    $this->assertEquals(TrackerInterface::STATUS_PENDING, $record['status']);
  }

  /**
   * Tests retrieving pending entities.
   *
   * @covers ::getPendingEntities
   */
  public function testGetPendingEntities(): void {
    // Add multiple entities.
    $this->tracker->addEntity('node', '1', TrackerInterface::OPERATION_PROCESS);
    $this->tracker->addEntity('node', '2', TrackerInterface::OPERATION_PROCESS);
    $this->tracker->addEntity('taxonomy_term', '5', TrackerInterface::OPERATION_DELETE);

    // Get pending entities.
    $pending = $this->tracker->getPendingEntities();

    $this->assertCount(3, $pending, 'Should return 3 pending entities');

    // Verify structure of returned data.
    $first = reset($pending);
    $this->assertArrayHasKey('id', $first);
    $this->assertArrayHasKey('entity_type', $first);
    $this->assertArrayHasKey('entity_id', $first);
    $this->assertArrayHasKey('operation', $first);
    $this->assertArrayHasKey('status', $first);
  }

  /**
   * Tests retrieving pending entities with limit.
   *
   * @covers ::getPendingEntities
   */
  public function testGetPendingEntitiesWithLimit(): void {
    // Add 5 entities.
    for ($i = 1; $i <= 5; $i++) {
      $this->tracker->addEntity('node', (string) $i, TrackerInterface::OPERATION_PROCESS);
    }

    // Get only 3.
    $pending = $this->tracker->getPendingEntities(3);

    $this->assertCount(3, $pending, 'Should return only 3 entities when limit is set');
  }

  /**
   * Tests that processed entities are not returned as pending.
   *
   * @covers ::getPendingEntities
   * @covers ::markAsProcessed
   */
  public function testGetPendingEntitiesExcludesProcessed(): void {
    // Add entities.
    $this->tracker->addEntity('node', '1', TrackerInterface::OPERATION_PROCESS);
    $this->tracker->addEntity('node', '2', TrackerInterface::OPERATION_PROCESS);

    // Get one and mark as processed.
    $pending = $this->tracker->getPendingEntities(1);
    $this->tracker->markAsProcessed((int) $pending[0]['id']);

    // Get pending again.
    $remaining = $this->tracker->getPendingEntities();

    $this->assertCount(1, $remaining, 'Should only return unprocessed entities');
    $this->assertEquals('2', $remaining[0]['entity_id']);
  }

  /**
   * Tests marking entity as processed.
   *
   * @covers ::markAsProcessed
   */
  public function testMarkAsProcessed(): void {
    // Add entity.
    $this->tracker->addEntity('node', '123', TrackerInterface::OPERATION_PROCESS);

    // Get the ID.
    $record = $this->database->select('entity_mesh_tracker', 't')
      ->fields('t', ['id'])
      ->condition('entity_type', 'node')
      ->condition('entity_id', '123')
      ->execute()
      ->fetchAssoc();

    $id = (int) $record['id'];

    // Mark as processed.
    $result = $this->tracker->markAsProcessed($id);

    $this->assertTrue($result, 'Should mark entity as processed successfully');

    // Verify in database.
    $updated = $this->database->select('entity_mesh_tracker', 't')
      ->fields('t')
      ->condition('id', $id)
      ->execute()
      ->fetchAssoc();

    $this->assertEquals(TrackerInterface::STATUS_PROCESSED, $updated['status']);
    $this->assertNotEmpty($updated['timestamp'], 'Timestamp should be updated');
  }

  /**
   * Tests marking entity as failed.
   *
   * @covers ::markAsFailed
   */
  public function testMarkAsFailed(): void {
    // Add entity.
    $this->tracker->addEntity('node', '123', TrackerInterface::OPERATION_PROCESS);

    // Get the ID.
    $record = $this->database->select('entity_mesh_tracker', 't')
      ->fields('t', ['id'])
      ->condition('entity_type', 'node')
      ->condition('entity_id', '123')
      ->execute()
      ->fetchAssoc();

    $id = (int) $record['id'];

    // Mark as failed.
    $result = $this->tracker->markAsFailed($id);

    $this->assertTrue($result, 'Should mark entity as failed successfully');

    // Verify in database.
    $updated = $this->database->select('entity_mesh_tracker', 't')
      ->fields('t')
      ->condition('id', $id)
      ->execute()
      ->fetchAssoc();

    $this->assertEquals(TrackerInterface::STATUS_FAILED, $updated['status']);
    $this->assertEquals(1, $updated['retry_count']);
    $this->assertNotEmpty($updated['timestamp'], 'Timestamp should be updated');
  }

  /**
   * Tests retry count increments on multiple failures.
   *
   * @covers ::markAsFailed
   */
  public function testMarkAsFailedIncrementsRetryCount(): void {
    // Add entity.
    $this->tracker->addEntity('node', '123', TrackerInterface::OPERATION_PROCESS);

    $record = $this->database->select('entity_mesh_tracker', 't')
      ->fields('t', ['id'])
      ->condition('entity_type', 'node')
      ->condition('entity_id', '123')
      ->execute()
      ->fetchAssoc();

    $id = (int) $record['id'];

    // Mark as failed multiple times.
    $this->tracker->markAsFailed($id);
    $this->tracker->markAsFailed($id);
    $this->tracker->markAsFailed($id);

    // Verify retry count.
    $updated = $this->database->select('entity_mesh_tracker', 't')
      ->fields('t', ['retry_count'])
      ->condition('id', $id)
      ->execute()
      ->fetchField();

    $this->assertEquals(3, $updated, 'Retry count should increment with each failure');
  }

  /**
   * Tests retrieving failed entities.
   *
   * @covers ::getFailedEntities
   */
  public function testGetFailedEntities(): void {
    // Add entities.
    $this->tracker->addEntity('node', '1', TrackerInterface::OPERATION_PROCESS);
    $this->tracker->addEntity('node', '2', TrackerInterface::OPERATION_PROCESS);
    $this->tracker->addEntity('node', '3', TrackerInterface::OPERATION_DELETE);

    // Mark some as failed.
    $pending = $this->tracker->getPendingEntities();
    $this->tracker->markAsFailed((int) $pending[0]['id']);
    $this->tracker->markAsFailed((int) $pending[1]['id']);

    // Get failed entities.
    $failed = $this->tracker->getFailedEntities();

    $this->assertCount(2, $failed, 'Should return 2 failed entities');

    // Verify structure.
    $first = reset($failed);
    $this->assertArrayHasKey('retry_count', $first);
  }

  /**
   * Tests deleting processed records older than specified time.
   *
   * @covers ::deleteProcessedRecords
   */
  public function testDeleteProcessedRecords(): void {
    // Add and process entities.
    $this->tracker->addEntity('node', '1', TrackerInterface::OPERATION_PROCESS);
    $this->tracker->addEntity('node', '2', TrackerInterface::OPERATION_PROCESS);

    $pending = $this->tracker->getPendingEntities();
    $this->tracker->markAsProcessed((int) $pending[0]['id']);
    $this->tracker->markAsProcessed((int) $pending[1]['id']);

    // Update one record to be older (simulate old record).
    // 8 days ago.
    $old_timestamp = \Drupal::time()->getRequestTime() - (8 * 24 * 60 * 60);
    $this->database->update('entity_mesh_tracker')
      ->fields(['timestamp' => $old_timestamp])
      ->condition('id', $pending[0]['id'])
      ->execute();

    // Delete records older than 7 days.
    $deleted = $this->tracker->deleteProcessedRecords(7);

    $this->assertEquals(1, $deleted, 'Should delete 1 old processed record');

    // Verify old record is gone.
    $exists = $this->database->select('entity_mesh_tracker', 't')
      ->condition('id', $pending[0]['id'])
      ->countQuery()
      ->execute()
      ->fetchField();

    $this->assertEquals(0, $exists, 'Old record should be deleted');

    // Verify recent record still exists.
    $exists = $this->database->select('entity_mesh_tracker', 't')
      ->condition('id', $pending[1]['id'])
      ->countQuery()
      ->execute()
      ->fetchField();

    $this->assertEquals(1, $exists, 'Recent record should still exist');
  }

  /**
   * Tests deleting entity from tracker.
   *
   * @covers ::deleteEntity
   */
  public function testDeleteEntity(): void {
    // Add entity.
    $this->tracker->addEntity('node', '123', TrackerInterface::OPERATION_PROCESS);

    // Verify it exists.
    $exists = $this->database->select('entity_mesh_tracker', 't')
      ->condition('entity_type', 'node')
      ->condition('entity_id', '123')
      ->countQuery()
      ->execute()
      ->fetchField();

    $this->assertEquals(1, $exists);

    // Delete it.
    $result = $this->tracker->deleteEntity('node', '123');

    $this->assertTrue($result, 'Should delete entity successfully');

    // Verify it's gone.
    $exists = $this->database->select('entity_mesh_tracker', 't')
      ->condition('entity_type', 'node')
      ->condition('entity_id', '123')
      ->countQuery()
      ->execute()
      ->fetchField();

    $this->assertEquals(0, $exists, 'Entity should be deleted from tracker');
  }

  /**
   * Tests getting count of pending entities by type.
   *
   * @covers ::getPendingCount
   */
  public function testGetPendingCount(): void {
    // Add entities of different types.
    $this->tracker->addEntity('node', '1', TrackerInterface::OPERATION_PROCESS);
    $this->tracker->addEntity('node', '2', TrackerInterface::OPERATION_PROCESS);
    $this->tracker->addEntity('taxonomy_term', '1', TrackerInterface::OPERATION_DELETE);

    // Get count for nodes.
    $count = $this->tracker->getPendingCount('node');
    $this->assertEquals(2, $count, 'Should return 2 pending nodes');

    // Get count for taxonomy terms.
    $count = $this->tracker->getPendingCount('taxonomy_term');
    $this->assertEquals(1, $count, 'Should return 1 pending taxonomy term');

    // Get total count.
    $count = $this->tracker->getPendingCount();
    $this->assertEquals(3, $count, 'Should return 3 total pending entities');
  }

  /**
   * Tests truncating the tracker table.
   *
   * @covers ::truncate
   */
  public function testTruncate(): void {
    // Add several entities.
    $this->tracker->addEntity('node', '1', TrackerInterface::OPERATION_PROCESS);
    $this->tracker->addEntity('node', '2', TrackerInterface::OPERATION_PROCESS);
    $this->tracker->addEntity('taxonomy_term', '1', TrackerInterface::OPERATION_DELETE);

    // Verify they exist.
    $count = $this->database->select('entity_mesh_tracker', 't')
      ->countQuery()
      ->execute()
      ->fetchField();

    $this->assertEquals(3, $count, 'Should have 3 records before truncate');

    // Truncate table.
    $result = $this->tracker->truncate();

    $this->assertTrue($result, 'Truncate should return TRUE');

    // Verify table is empty.
    $count = $this->database->select('entity_mesh_tracker', 't')
      ->countQuery()
      ->execute()
      ->fetchField();

    $this->assertEquals(0, $count, 'Should have 0 records after truncate');
  }

  /**
   * Tests adding multiple entities at once.
   *
   * @covers ::addMultipleEntities
   */
  public function testAddMultipleEntities(): void {
    // Prepare multiple entities.
    $entities = [
      ['entity_type' => 'node', 'entity_id' => '1', 'operation' => TrackerInterface::OPERATION_PROCESS],
      ['entity_type' => 'node', 'entity_id' => '2', 'operation' => TrackerInterface::OPERATION_PROCESS],
      ['entity_type' => 'taxonomy_term', 'entity_id' => '5', 'operation' => TrackerInterface::OPERATION_DELETE],
      ['entity_type' => 'node', 'entity_id' => '3', 'operation' => TrackerInterface::OPERATION_DELETE],
    ];

    // Add multiple entities.
    $result = $this->tracker->addMultipleEntities($entities);

    $this->assertTrue($result, 'Should add multiple entities successfully');

    // Verify all were added.
    $count = $this->database->select('entity_mesh_tracker', 't')
      ->countQuery()
      ->execute()
      ->fetchField();

    $this->assertEquals(4, $count, 'Should have 4 records in database');

    // Verify individual records.
    $record = $this->database->select('entity_mesh_tracker', 't')
      ->fields('t')
      ->condition('entity_type', 'node')
      ->condition('entity_id', '1')
      ->execute()
      ->fetchAssoc();

    $this->assertNotEmpty($record);
    $this->assertEquals(TrackerInterface::OPERATION_PROCESS, $record['operation']);
    $this->assertEquals(TrackerInterface::STATUS_PENDING, $record['status']);
  }

  /**
   * Tests adding multiple entities with empty array.
   *
   * @covers ::addMultipleEntities
   */
  public function testAddMultipleEntitiesEmpty(): void {
    // Try with empty array.
    $result = $this->tracker->addMultipleEntities([]);

    $this->assertTrue($result, 'Should return TRUE even with empty array');

    // Verify no records added.
    $count = $this->database->select('entity_mesh_tracker', 't')
      ->countQuery()
      ->execute()
      ->fetchField();

    $this->assertEquals(0, $count, 'Should have 0 records in database');
  }

}
