<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_mesh\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\entity_mesh\TrackerManager;
use Drupal\entity_mesh\TrackerInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Tests the Entity Mesh Tracker Manager service.
 *
 * @group entity_mesh
 * @coversDefaultClass \Drupal\entity_mesh\TrackerManager
 */
class TrackerManagerTest extends UnitTestCase {

  /**
   * The tracker service mock.
   *
   * @var \Drupal\entity_mesh\TrackerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $tracker;

  /**
   * The tracker manager under test.
   *
   * @var \Drupal\entity_mesh\TrackerManager
   */
  protected $trackerManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create mock for tracker service.
    $this->tracker = $this->createMock(TrackerInterface::class);

    // Create tracker manager instance with mocked dependencies.
    $this->trackerManager = new TrackerManager($this->tracker);
  }

  /**
   * Tests preprocessing a content entity.
   *
   * @covers ::preprocessEntity
   */
  public function testPreprocessEntity(): void {
    // Create a mock content entity.
    $entity = $this->createMock(ContentEntityInterface::class);

    // Configure the entity mock to return expected values.
    $entity->expects($this->once())
      ->method('getEntityTypeId')
      ->willReturn('node');

    $entity->expects($this->once())
      ->method('id')
      ->willReturn('123');

    // Use reflection to test the protected method.
    $reflection = new \ReflectionClass($this->trackerManager);
    $method = $reflection->getMethod('preprocessEntity');
    $method->setAccessible(TRUE);

    // Call the protected method.
    $result = $method->invoke($this->trackerManager, $entity, TrackerInterface::OPERATION_PROCESS);

    // Assert the returned array has the expected structure.
    $this->assertIsArray($result, 'Should return an array');
    $this->assertArrayHasKey('entity_type', $result, 'Should have entity_type key');
    $this->assertArrayHasKey('entity_id', $result, 'Should have entity_id key');
    $this->assertArrayHasKey('operation', $result, 'Should have operation key');

    // Assert the values are correct.
    $this->assertEquals('node', $result['entity_type'], 'Entity type should be node');
    $this->assertEquals('123', $result['entity_id'], 'Entity ID should be 123');
    $this->assertEquals(TrackerInterface::OPERATION_PROCESS, $result['operation'], 'Operation should be OPERATION_PROCESS');
  }

  /**
   * Tests preprocessing entity with DELETE operation.
   *
   * @covers ::preprocessEntity
   */
  public function testPreprocessEntityWithDeleteOperation(): void {
    // Create a mock content entity.
    $entity = $this->createMock(ContentEntityInterface::class);

    $entity->expects($this->once())
      ->method('getEntityTypeId')
      ->willReturn('taxonomy_term');

    $entity->expects($this->once())
      ->method('id')
      ->willReturn('456');

    // Use reflection to test the protected method.
    $reflection = new \ReflectionClass($this->trackerManager);
    $method = $reflection->getMethod('preprocessEntity');
    $method->setAccessible(TRUE);

    // Call the protected method with DELETE operation.
    $result = $method->invoke($this->trackerManager, $entity, TrackerInterface::OPERATION_DELETE);

    // Assert the values.
    $this->assertEquals('taxonomy_term', $result['entity_type']);
    $this->assertEquals('456', $result['entity_id']);
    $this->assertEquals(TrackerInterface::OPERATION_DELETE, $result['operation']);
  }

  /**
   * Tests preprocessing multiple entities returns correct format.
   *
   * @covers ::preprocessEntity
   */
  public function testPreprocessEntityReturnsCorrectFormat(): void {
    // Create a mock content entity.
    $entity = $this->createMock(ContentEntityInterface::class);

    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('id')->willReturn('789');

    // Use reflection to test the protected method.
    $reflection = new \ReflectionClass($this->trackerManager);
    $method = $reflection->getMethod('preprocessEntity');
    $method->setAccessible(TRUE);

    $result = $method->invoke($this->trackerManager, $entity, TrackerInterface::OPERATION_PROCESS);

    // Verify the format matches what addEntity expects.
    $this->assertCount(3, $result, 'Should have exactly 3 elements');
    $this->assertArrayNotHasKey('status', $result, 'Should not include status field');
    $this->assertArrayNotHasKey('created', $result, 'Should not include created field');
  }

  /**
   * Tests preprocessing multiple entities at once.
   *
   * @covers ::preprocessEntities
   */
  public function testPreprocessEntities(): void {
    // Create mock entities.
    $entity1 = $this->createMock(ContentEntityInterface::class);
    $entity1->method('getEntityTypeId')->willReturn('node');
    $entity1->method('id')->willReturn('100');

    $entity2 = $this->createMock(ContentEntityInterface::class);
    $entity2->method('getEntityTypeId')->willReturn('node');
    $entity2->method('id')->willReturn('101');

    $entity3 = $this->createMock(ContentEntityInterface::class);
    $entity3->method('getEntityTypeId')->willReturn('taxonomy_term');
    $entity3->method('id')->willReturn('50');

    $entities = [$entity1, $entity2, $entity3];

    // Use reflection to test the protected method.
    $reflection = new \ReflectionClass($this->trackerManager);
    $method = $reflection->getMethod('preprocessEntities');
    $method->setAccessible(TRUE);

    // Call the protected method.
    $result = $method->invoke($this->trackerManager, $entities, TrackerInterface::OPERATION_PROCESS);

    // Assert the result is an array.
    $this->assertIsArray($result, 'Should return an array');
    $this->assertCount(3, $result, 'Should have 3 preprocessed entities');

    // Verify first entity.
    $this->assertEquals('node', $result[0]['entity_type']);
    $this->assertEquals('100', $result[0]['entity_id']);
    $this->assertEquals(TrackerInterface::OPERATION_PROCESS, $result[0]['operation']);

    // Verify second entity.
    $this->assertEquals('node', $result[1]['entity_type']);
    $this->assertEquals('101', $result[1]['entity_id']);
    $this->assertEquals(TrackerInterface::OPERATION_PROCESS, $result[1]['operation']);

    // Verify third entity.
    $this->assertEquals('taxonomy_term', $result[2]['entity_type']);
    $this->assertEquals('50', $result[2]['entity_id']);
    $this->assertEquals(TrackerInterface::OPERATION_PROCESS, $result[2]['operation']);
  }

  /**
   * Tests preprocessing empty array of entities.
   *
   * @covers ::preprocessEntities
   */
  public function testPreprocessEntitiesEmpty(): void {
    // Use reflection to test the protected method.
    $reflection = new \ReflectionClass($this->trackerManager);
    $method = $reflection->getMethod('preprocessEntities');
    $method->setAccessible(TRUE);

    // Call with empty array.
    $result = $method->invoke($this->trackerManager, [], TrackerInterface::OPERATION_PROCESS);

    // Assert returns empty array.
    $this->assertIsArray($result, 'Should return an array');
    $this->assertCount(0, $result, 'Should return empty array');
  }

  /**
   * Tests adding entity to tracker for processing.
   *
   * @covers ::addTrackedEntity
   */
  public function testAddTrackedEntity(): void {
    // Create a mock entity.
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('id')->willReturn('123');

    // Configure tracker mock to expect addEntity call with OPERATION_PROCESS.
    $this->tracker->expects($this->once())
      ->method('addEntity')
      ->with('node', '123', TrackerInterface::OPERATION_PROCESS)
      ->willReturn(TRUE);

    // Call the method.
    $result = $this->trackerManager->addTrackedEntity($entity);

    $this->assertTrue($result, 'Should return TRUE when entity is added');
  }

  /**
   * Tests adding entity to tracker for deletion.
   *
   * @covers ::deleteTrackedEntity
   */
  public function testDeleteTrackedEntity(): void {
    // Create a mock entity.
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('taxonomy_term');
    $entity->method('id')->willReturn('456');

    // Configure tracker mock to expect addEntity call with OPERATION_DELETE.
    $this->tracker->expects($this->once())
      ->method('addEntity')
      ->with('taxonomy_term', '456', TrackerInterface::OPERATION_DELETE)
      ->willReturn(TRUE);

    // Call the method.
    $result = $this->trackerManager->deleteTrackedEntity($entity);

    $this->assertTrue($result, 'Should return TRUE when entity is added');
  }

  /**
   * Tests adding multiple entities to tracker for processing.
   *
   * @covers ::addTrackedEntities
   */
  public function testAddTrackedEntities(): void {
    // Create mock entities.
    $entity1 = $this->createMock(ContentEntityInterface::class);
    $entity1->method('getEntityTypeId')->willReturn('node');
    $entity1->method('id')->willReturn('100');

    $entity2 = $this->createMock(ContentEntityInterface::class);
    $entity2->method('getEntityTypeId')->willReturn('node');
    $entity2->method('id')->willReturn('101');

    $entity3 = $this->createMock(ContentEntityInterface::class);
    $entity3->method('getEntityTypeId')->willReturn('taxonomy_term');
    $entity3->method('id')->willReturn('50');

    $entities = [$entity1, $entity2, $entity3];

    // Configure tracker mock to expect addMultipleEntities call.
    $this->tracker->expects($this->once())
      ->method('addMultipleEntities')
      ->with($this->callback(function ($data) {
        // Verify the data structure.
        if (count($data) !== 3) {
          return FALSE;
        }

        // Check first entity.
        if ($data[0]['entity_type'] !== 'node' || $data[0]['entity_id'] !== '100' || $data[0]['operation'] !== TrackerInterface::OPERATION_PROCESS) {
          return FALSE;
        }

        // Check second entity.
        if ($data[1]['entity_type'] !== 'node' || $data[1]['entity_id'] !== '101' || $data[1]['operation'] !== TrackerInterface::OPERATION_PROCESS) {
          return FALSE;
        }

        // Check third entity.
        if ($data[2]['entity_type'] !== 'taxonomy_term' || $data[2]['entity_id'] !== '50' || $data[2]['operation'] !== TrackerInterface::OPERATION_PROCESS) {
          return FALSE;
        }

        return TRUE;
      }))
      ->willReturn(TRUE);

    // Call the method.
    $result = $this->trackerManager->addTrackedEntities($entities);

    $this->assertTrue($result, 'Should return TRUE when entities are added');
  }

  /**
   * Tests adding empty array of entities.
   *
   * @covers ::addTrackedEntities
   */
  public function testAddTrackedEntitiesEmpty(): void {
    // Configure tracker mock to expect addMultipleEntities with empty array.
    $this->tracker->expects($this->once())
      ->method('addMultipleEntities')
      ->with([])
      ->willReturn(TRUE);

    // Call with empty array.
    $result = $this->trackerManager->addTrackedEntities([]);

    $this->assertTrue($result, 'Should return TRUE even with empty array');
  }

}
