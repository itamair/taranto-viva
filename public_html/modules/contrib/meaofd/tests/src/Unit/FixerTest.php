<?php

declare(strict_types=1);

namespace Drupal\Tests\meaofd\Unit;

require_once __DIR__ . '/../../../src/Services/Fixer.php';

use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\meaofd\Services\Fixer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \Drupal\meaofd\Services\Fixer
 */
class FixerTest extends TestCase {

  /**
   * Test entity type ID.
   *
   * @const string
   */
  const TEST_ENTITY_TYPE_ID = 'test_entity_type';

  /**
   * Test entity changes.
   *
   * @const array
   */
  const TEST_ENTITY_CHANGES = [
    'Change 1',
    'Change 2',
    'Change 3',
  ];

  /**
   * The Mismatched Entity And/Or Field Definitions fixer service.
   *
   * @var \Drupal\meaofd\Services\Fixer
   */
  protected $fixer;

  /**
   * The logger service mock.
   *
   * @var \Psr\Log\LoggerInterface|PHPUnit\Framework\MockObject\MockObject
   */
  protected $logger;

  /**
   * The entity type manager service mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The entity definition update manager service mock.
   *
   * @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface|PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityDefinitionUpdateManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create mocks for the logger, entity type
    // manager, and entity definition services.
    $this->logger = $this->createMock(LoggerInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityDefinitionUpdateManager = $this->createMock(EntityDefinitionUpdateManagerInterface::class);

    // Create a new Fixer instance.
    $this->fixer = new Fixer(
      $this->logger,
      $this->entityTypeManager,
      $this->entityDefinitionUpdateManager,
    );
  }

  /**
   * @covers ::__construct
   */
  public function testServiceInstantiation(): void {
    $this->assertInstanceOf(Fixer::class, $this->fixer);
  }

  /**
   * @covers ::getChangeSummary
   */
  public function testGetChangeSummary(): void {
    $expectedSummary = [self::TEST_ENTITY_TYPE_ID => self::TEST_ENTITY_CHANGES];

    $this->entityDefinitionUpdateManager
      ->expects($this->once())
      ->method('getChangeSummary')
      ->willReturn($expectedSummary);

    $result = $this->fixer->getChangeSummary();

    $this->assertSame($expectedSummary, $result);
  }

  /**
   * @covers ::fix
   */
  public function testFix(): void {
    $this->entityDefinitionUpdateManager
      ->expects($this->once())
      ->method('getChangeSummary')
      ->willReturn([self::TEST_ENTITY_TYPE_ID => self::TEST_ENTITY_CHANGES]);

    $entityDefinitionMock = $this->createMock(EntityTypeInterface::class);
    $this->entityTypeManager
      ->expects($this->once())
      ->method('getDefinition')
      ->with(self::TEST_ENTITY_TYPE_ID)
      ->willReturn($entityDefinitionMock);

    $this->entityDefinitionUpdateManager
      ->expects($this->once())
      ->method('installEntityType')
      ->with($entityDefinitionMock);

    $this->logger
      ->expects($this->atLeastOnce())
      ->method('info')
      ->with('Entity cache definitions have been rebuilt.');

    $result = $this->fixer->fix(self::TEST_ENTITY_TYPE_ID);

    $this->assertSame([self::TEST_ENTITY_TYPE_ID], $result);
  }

  /**
   * @covers ::entityTypeHasChanges
   */
  public function testEntityTypeHasChanges(): void {
    $this->entityDefinitionUpdateManager
      ->expects($this->once())
      ->method('getChangeSummary')
      ->willReturn([self::TEST_ENTITY_TYPE_ID => self::TEST_ENTITY_CHANGES]);

    $result = $this->fixer->entityTypeHasChanges(self::TEST_ENTITY_TYPE_ID);
    $this->assertTrue($result, 'Expected TRUE when entity type has changes.');

    $this->entityDefinitionUpdateManager = $this->createMock(EntityDefinitionUpdateManagerInterface::class);
    $this->fixer = new Fixer(
      $this->logger,
      $this->entityTypeManager,
      $this->entityDefinitionUpdateManager,
    );

    $this->entityDefinitionUpdateManager
      ->expects($this->once())
      ->method('getChangeSummary')
      ->willReturn(['other_entity_type' => self::TEST_ENTITY_CHANGES]);

    $result = $this->fixer->entityTypeHasChanges(self::TEST_ENTITY_TYPE_ID);
    $this->assertFalse($result, 'Expected FALSE when entity type has no changes.');
  }

}
