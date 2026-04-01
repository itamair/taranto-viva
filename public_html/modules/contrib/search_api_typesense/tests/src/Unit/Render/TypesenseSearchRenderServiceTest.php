<?php

declare(strict_types=1);

namespace Drupal\Tests\search_api_typesense\Unit\Render;

use Drupal\search_api\Backend\BackendInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\ServerInterface;
use Drupal\search_api_typesense\Api\SearchApiTypesenseException;
use Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend;
use Drupal\search_api_typesense\Render\TypesenseSearchRenderContext;
use Drupal\search_api_typesense\Render\TypesenseSearchRenderService;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Tests for the TypesenseSearchRenderService.
 *
 * @group search_api_typesense
 *
 * @coversDefaultClass \Drupal\search_api_typesense\Render\TypesenseSearchRenderService
 */
class TypesenseSearchRenderServiceTest extends UnitTestCase {

  /**
   * The service under test.
   *
   * @var \Drupal\search_api_typesense\Render\TypesenseSearchRenderService
   */
  protected TypesenseSearchRenderService $service;

  /**
   * Mock backend.
   *
   * @var \Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend|\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $backend;

  /**
   * Mock event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $eventDispatcher;

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

    $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    $this->service = new TypesenseSearchRenderService($this->eventDispatcher);
    $this->backend = $this->createMock(SearchApiTypesenseBackend::class);
    $this->index = $this->createMock(IndexInterface::class);
  }

  /**
   * Tests validateBackend with valid Typesense backend.
   *
   * @covers ::validateBackend
   */
  public function testValidateBackendWithTypesenseBackend(): void {
    $server = $this->createMock(ServerInterface::class);
    $server->method('getBackend')->willReturn($this->backend);

    $result = $this->service->validateBackend($server);

    self::assertSame($this->backend, $result);
  }

  /**
   * Tests validateBackend with invalid backend.
   *
   * @covers ::validateBackend
   */
  public function testValidateBackendWithInvalidBackend(): void {
    $server = $this->createMock(ServerInterface::class);
    $invalidBackend = $this->createMock(BackendInterface::class);
    $server->method('getBackend')->willReturn($invalidBackend);

    $this->expectException(SearchApiTypesenseException::class);
    $this->expectExceptionMessage('The server must use the Typesense backend.');

    $this->service->validateBackend($server);
  }

  /**
   * Tests prepareCollectionData with single index.
   *
   * @covers ::prepareCollectionData
   */
  public function testPrepareCollectionDataSingleIndex(): void {
    $this->backend->method('getCollectionName')
      ->with($this->index)
      ->willReturn('test_collection');

    $this->backend->method('getCollectionSpecificSearchParameters')
      ->with($this->index)
      ->willReturn(['query_by' => 'title,body']);

    $this->backend->method('getCollectionRenderParameters')
      ->with($this->index)
      ->willReturn(['collection_name' => 'test_collection', 'collection_label' => 'Test Collection']);

    $this->backend->method('getFacetsForCollection')
      ->with($this->index)
      ->willReturn(['category' => ['name' => 'category', 'type' => 'string', 'label' => 'Category']]);

    $result = $this->service->prepareCollectionData([$this->index], $this->backend);

    $expected = [
      'collection_specific_search_parameters' => [
        'test_collection' => ['query_by' => 'title,body'],
      ],
      'collection_render_parameters' => [
        ['collection_name' => 'test_collection', 'collection_label' => 'Test Collection'],
      ],
      'facets' => [
        'category' => ['name' => 'category', 'type' => 'string', 'label' => 'Category'],
      ],
    ];

    self::assertEquals($expected, $result);
  }

  /**
   * Tests buildSearchRender with minimal context.
   *
   * @covers ::buildSearchRender
   */
  public function testBuildSearchRenderMinimal(): void {
    $this->backend->method('getConfiguration')
      ->willReturn([
        'nodes' => [
          ['host' => 'localhost', 'port' => '8108', 'protocol' => 'http'],
        ],
      ]);

    $this->backend->method('getCollectionName')
      ->with($this->index)
      ->willReturn('test_collection');

    $this->backend->method('getCollectionSpecificSearchParameters')
      ->with($this->index)
      ->willReturn(['query_by' => 'title']);

    $this->backend->method('getCollectionRenderParameters')
      ->with($this->index)
      ->willReturn(['collection_name' => 'test_collection']);

    $this->backend->method('getFacetsForCollection')
      ->with($this->index)
      ->willReturn([]);

    // Expect that the event dispatcher is called.
    $this->eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->willReturnArgument(0);

    $block_uuid = 'test-uuid';
    $context = new TypesenseSearchRenderContext(
      backend: $this->backend,
      indexes: [$this->index],
      apiKey: 'test_api_key',
      blockUuid: $block_uuid,
      hitsPerPage: 10,
      debug: FALSE,
    );

    $result = $this->service->buildSearchRender($context);

    self::assertArrayHasKey('content', $result);
    self::assertEquals('search_api_typesense_search', $result['content']['#theme']);
    self::assertArrayHasKey('#attached', $result);
    self::assertArrayHasKey('drupalSettings', $result['#attached']);

    $settings = $result['#attached']['drupalSettings']['search_api_typesense']['blocks'][$block_uuid];
    self::assertEquals('test_api_key', $settings['server']['apiKey']);
    self::assertEquals(10, $settings['hits_per_page']);
    self::assertArrayNotHasKey('debug', $settings);
    self::assertArrayNotHasKey('current_langcode', $settings);
  }

  /**
   * Tests buildSearchRender with full context.
   *
   * @covers ::buildSearchRender
   */
  public function testBuildSearchRenderWithFullContext(): void {
    $this->backend->method('getConfiguration')
      ->willReturn([
        'nodes' => [
          ['host' => 'localhost', 'port' => '8108', 'protocol' => 'http'],
        ],
      ]);

    $this->backend->method('getCollectionName')
      ->with($this->index)
      ->willReturn('test_collection');

    $this->backend->method('getCollectionSpecificSearchParameters')
      ->with($this->index)
      ->willReturn(['query_by' => 'title']);

    $this->backend->method('getCollectionRenderParameters')
      ->with($this->index)
      ->willReturn(['collection_name' => 'test_collection']);

    $this->backend->method('getFacetsForCollection')
      ->with($this->index)
      ->willReturn([]);

    // Expect that the event dispatcher is called.
    $this->eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->willReturnArgument(0);

    $block_uuid = 'test-uuid';
    $context = new TypesenseSearchRenderContext(
      backend: $this->backend,
      indexes: [$this->index],
      apiKey: 'test_api_key',
      blockUuid: $block_uuid,
      hitsPerPage: 15,
      debug: TRUE,
      currentLanguage: 'en',
      additionalSettings: ['custom_setting' => 'custom_value'],
    );

    $result = $this->service->buildSearchRender($context);

    $settings = $result['#attached']['drupalSettings']['search_api_typesense']['blocks'][$block_uuid];
    self::assertEquals('test_api_key', $settings['server']['apiKey']);
    self::assertEquals(15, $settings['hits_per_page']);
    self::assertTrue($settings['debug']);
    self::assertEquals('en', $settings['current_langcode']);
    self::assertEquals('custom_value', $settings['custom_setting']);
  }

}
