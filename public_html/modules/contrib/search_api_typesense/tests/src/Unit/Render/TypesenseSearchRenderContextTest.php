<?php

declare(strict_types=1);

namespace Drupal\Tests\search_api_typesense\Unit\Render;

use Drupal\search_api\IndexInterface;
use Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend;
use Drupal\search_api_typesense\Render\TypesenseSearchRenderContext;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests for the TypesenseSearchRenderContext.
 *
 * @group search_api_typesense
 *
 * @coversDefaultClass \Drupal\search_api_typesense\Render\TypesenseSearchRenderContext
 */
class TypesenseSearchRenderContextTest extends UnitTestCase {

  /**
   * Mock backend.
   *
   * @var \Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend|\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $backend;

  /**
   * Mock index.
   *
   * @var \Drupal\search_api\IndexInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $index;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->backend = $this->createMock(SearchApiTypesenseBackend::class);
    $this->index = $this->createMock(IndexInterface::class);
  }

  /**
   * Tests context creation with minimal parameters.
   *
   * @covers ::__construct
   * @covers ::getBackend
   * @covers ::getIndexes
   * @covers ::getApiKey
   * @covers ::getHitsPerPage
   * @covers ::isDebugEnabled
   * @covers ::getCurrentLanguage
   * @covers ::getAdditionalSettings
   */
  public function testContextCreationMinimal(): void {
    $context = new TypesenseSearchRenderContext(
      backend: $this->backend,
      indexes: [$this->index],
      apiKey: 'test_key',
      blockUuid: 'test-uuid',
    );

    self::assertSame($this->backend, $context->getBackend());
    self::assertEquals([$this->index], $context->getIndexes());
    self::assertEquals('test_key', $context->getApiKey());
    // Default value.
    self::assertEquals(9, $context->getHitsPerPage());
    // Default value.
    self::assertFalse($context->isDebugEnabled());
    // Default value.
    self::assertNull($context->getCurrentLanguage());
    // Default value.
    self::assertEquals([], $context->getAdditionalSettings());
  }

  /**
   * Tests context creation with all parameters.
   *
   * @covers ::__construct
   * @covers ::getBackend
   * @covers ::getIndexes
   * @covers ::getApiKey
   * @covers ::getHitsPerPage
   * @covers ::isDebugEnabled
   * @covers ::getCurrentLanguage
   * @covers ::getAdditionalSettings
   */
  public function testContextCreationFull(): void {
    $indexes = [$this->index];
    $additionalSettings = ['custom' => 'value', 'another' => 'setting'];

    $context = new TypesenseSearchRenderContext(
      backend: $this->backend,
      indexes: $indexes,
      apiKey: 'full_test_key',
      blockUuid: 'full-test-uuid',
      hitsPerPage: 25,
      debug: TRUE,
      currentLanguage: 'fr',
      additionalSettings: $additionalSettings,
    );

    self::assertSame($this->backend, $context->getBackend());
    self::assertEquals($indexes, $context->getIndexes());
    self::assertEquals('full_test_key', $context->getApiKey());
    self::assertEquals(25, $context->getHitsPerPage());
    self::assertTrue($context->isDebugEnabled());
    self::assertEquals('fr', $context->getCurrentLanguage());
    self::assertEquals($additionalSettings, $context->getAdditionalSettings());
  }

  /**
   * Tests context with multiple indexes.
   *
   * @covers ::getIndexes
   */
  public function testContextWithMultipleIndexes(): void {
    $index2 = $this->createMock(IndexInterface::class);
    $indexes = [$this->index, $index2];

    $context = new TypesenseSearchRenderContext(
      backend: $this->backend,
      indexes: $indexes,
      apiKey: 'multi_index_key',
      blockUuid: 'multi-index-uuid',
    );

    self::assertCount(2, $context->getIndexes());
    self::assertEquals($indexes, $context->getIndexes());
  }

}
