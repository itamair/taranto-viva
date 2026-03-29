<?php

namespace Drupal\Tests\entity_mesh\Unit;

use Drupal\Core\Access\AccessManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\entity_mesh\EntityRender;
use Drupal\entity_mesh\Language\LanguageNegotiatorSwitcher;
use Drupal\entity_mesh\RepositoryInterface;
use Drupal\entity_mesh\Target;
use Drupal\entity_mesh\TargetInterface;
use Drupal\entity_mesh\ThemeSwitcher;
use Drupal\entity_mesh\TrackerManagerInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the EntityRender class.
 *
 * @group entity_mesh
 * @coversDefaultClass \Drupal\entity_mesh\EntityRender
 */
class EntityRenderTest extends UnitTestCase {

  /**
   * The entity render service under test.
   *
   * @var \Drupal\entity_mesh\EntityRender
   */
  protected $entityRender;

  /**
   * The mocked request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $requestStack;

  /**
   * The mocked repository.
   *
   * @var \Drupal\entity_mesh\RepositoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $repository;

  /**
   * The mocked entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The mocked language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $languageManager;

  /**
   * The mocked config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * The mocked renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $renderer;

  /**
   * The mocked account switcher.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $accountSwitcher;

  /**
   * The mocked language negotiator switcher.
   *
   * @var \Drupal\entity_mesh\Language\LanguageNegotiatorSwitcher|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $languageNegotiatorSwitcher;

  /**
   * The mocked module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $moduleHandler;

  /**
   * The mocked access manager.
   *
   * @var \Drupal\Core\Access\AccessManager|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $accessManager;

  /**
   * The mocked theme switcher.
   *
   * @var \Drupal\entity_mesh\ThemeSwitcher|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $themeSwitcher;

  /**
   * The mocked tracker manager.
   *
   * @var \Drupal\entity_mesh\TrackerManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $trackerManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->repository = $this->createMock(RepositoryInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->languageManager = $this->createMock(LanguageManagerInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->renderer = $this->createMock(RendererInterface::class);
    $this->accountSwitcher = $this->createMock(AccountSwitcherInterface::class);
    $this->languageNegotiatorSwitcher = $this->createMock(LanguageNegotiatorSwitcher::class);
    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $this->accessManager = $this->createMock(AccessManager::class);
    $this->themeSwitcher = $this->createMock(ThemeSwitcher::class);
    $this->trackerManager = $this->createMock(TrackerManagerInterface::class);
    $this->requestStack = $this->createMock(RequestStack::class);

    $this->entityRender = new TestableEntityRender(
      $this->repository,
      $this->entityTypeManager,
      $this->languageManager,
      $this->configFactory,
      $this->renderer,
      $this->accountSwitcher,
      $this->languageNegotiatorSwitcher,
      $this->moduleHandler,
      $this->accessManager,
      $this->themeSwitcher,
      $this->trackerManager
    );
  }

  /**
   * Tests setDataTargetFromRoute with root installation.
   *
   * @covers ::setDataTargetFromRoute
   */
  public function testSetDataTargetFromRouteRootInstallation() {
    $request = new Request();
    $this->requestStack->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $target = Target::create($this->requestStack);
    $target->setPath('/node/1');

    $routeMatch = [
      '_route' => 'entity.node.canonical',
      '_entity' => $this->createMockEntity('node', '1'),
    ];

    $this->entityRender->setMockRouteMatch($routeMatch);
    $this->entityRender->callSetDataTargetFromRoute($target);

    $this->assertEquals('node', $target->getEntityType());
    $this->assertEquals('1', $target->getEntityId());
  }

  /**
   * Tests setDataTargetFromRoute with subdirectory installation.
   *
   * @covers ::setDataTargetFromRoute
   */
  public function testSetDataTargetFromRouteSubdirectoryInstallation() {
    $request = new Request();
    $request->server->set('SCRIPT_NAME', '/drupal_site/index.php');
    $request->server->set('SCRIPT_FILENAME', '/var/www/html/drupal_site/index.php');
    $this->requestStack->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $target = Target::create($this->requestStack);
    $target->setPath('/drupal_site/node/1');

    $routeMatch = [
      '_route' => 'entity.node.canonical',
      '_entity' => $this->createMockEntity('node', '1'),
    ];

    $this->entityRender->setMockRouteMatch($routeMatch);
    $this->entityRender->callSetDataTargetFromRoute($target);

    $this->assertEquals('node', $target->getEntityType());
    $this->assertEquals('1', $target->getEntityId());
  }

  /**
   * Tests setDataTargetFromRoute with nested subdirectory installation.
   *
   * @covers ::setDataTargetFromRoute
   */
  public function testSetDataTargetFromRouteNestedSubdirectoryInstallation() {
    $request = new Request();
    $request->server->set('SCRIPT_NAME', '/sites/drupal_site/index.php');
    $request->server->set('SCRIPT_FILENAME', '/var/www/html/sites/drupal_site/index.php');
    $this->requestStack->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $target = Target::create($this->requestStack);
    $target->setPath('/sites/drupal_site/node/1');

    $routeMatch = [
      '_route' => 'entity.node.canonical',
      '_entity' => $this->createMockEntity('node', '1'),
    ];

    $this->entityRender->setMockRouteMatch($routeMatch);
    $this->entityRender->callSetDataTargetFromRoute($target);

    $this->assertEquals('node', $target->getEntityType());
    $this->assertEquals('1', $target->getEntityId());
  }

  /**
   * Tests setDataTargetFromRoute with trailing slash in base path.
   *
   * @covers ::setDataTargetFromRoute
   */
  public function testSetDataTargetFromRouteTrailingSlash() {
    $request = new Request();
    $request->server->set('SCRIPT_NAME', '/drupal_site/index.php');
    $request->server->set('SCRIPT_FILENAME', '/var/www/html/drupal_site/index.php');
    $this->requestStack->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $target = Target::create($this->requestStack);
    $target->setPath('/drupal_site/node/1');

    $routeMatch = [
      '_route' => 'entity.node.canonical',
      '_entity' => $this->createMockEntity('node', '1'),
    ];

    $this->entityRender->setMockRouteMatch($routeMatch);
    $this->entityRender->callSetDataTargetFromRoute($target);

    $this->assertEquals('node', $target->getEntityType());
    $this->assertEquals('1', $target->getEntityId());
  }

  /**
   * Tests setDataTargetFromRoute when route matching fails.
   *
   * @covers ::setDataTargetFromRoute
   */
  public function testSetDataTargetFromRouteBrokenLink() {
    $request = new Request();
    $request->server->set('SCRIPT_NAME', '/drupal_site/index.php');
    $this->requestStack->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $target = Target::create($this->requestStack);
    $target->setPath('/drupal_site/non-existent');

    $this->entityRender->setRouteMatchException(new \Exception('Route not found'));
    $this->entityRender->callSetDataTargetFromRoute($target);

    $this->assertEquals('broken-link', $target->getSubcategory());
  }

  /**
   * Tests setDataTargetFromRoute with no request.
   *
   * @covers ::setDataTargetFromRoute
   */
  public function testSetDataTargetFromRouteNoRequest() {
    $this->requestStack->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn(NULL);

    $target = Target::create($this->requestStack);
    $target->setPath('/node/1');

    $routeMatch = [
      '_route' => 'entity.node.canonical',
      '_entity' => $this->createMockEntity('node', '1'),
    ];

    $this->entityRender->setMockRouteMatch($routeMatch);
    $this->entityRender->callSetDataTargetFromRoute($target);

    $this->assertEquals('node', $target->getEntityType());
    $this->assertEquals('1', $target->getEntityId());
  }

  /**
   * Tests setDataTargetFromRoute with view route.
   *
   * @covers ::setDataTargetFromRoute
   */
  public function testSetDataTargetFromRouteViewRoute() {
    $request = new Request();
    $request->server->set('SCRIPT_NAME', '/drupal_site/index.php');
    $this->requestStack->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $target = Target::create($this->requestStack);
    $target->setPath('/drupal_site/admin/content');

    $routeMatch = [
      '_route' => 'view.content.page_1',
      'view_id' => 'content',
      'display_id' => 'page_1',
    ];

    $this->entityRender->setMockRouteMatch($routeMatch);
    $this->entityRender->callSetDataTargetFromRoute($target);

    $this->assertEquals('view', $target->getEntityType());
    $this->assertEquals('content.page_1', $target->getEntityId());
  }

  /**
   * Tests that processInternalHref sets langcode from path, not source entity.
   *
   * This is the core fix: when a Spanish node references /media/123 (no
   * language prefix in the URL), the target langcode should be null -- not
   * inherited from the source node's language. This prevents false "access
   * denied" results for untranslated media.
   *
   * @covers ::processInternalHref
   */
  public function testProcessInternalHrefLangcodeFromPathOnly() {
    $request = new Request();
    $this->requestStack->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $target = Target::create($this->requestStack);
    $target->setLinkType('internal');
    $target->setPath('/media/123');

    // Repository returns no langcode from path (no language prefix in URL).
    $this->repository->expects($this->once())
      ->method('getLangcodeFromPath')
      ->with('/media/123')
      ->willReturn(NULL);

    $this->repository->expects($this->once())
      ->method('getPathWithoutLangPrefix')
      ->with('/media/123')
      ->willReturn('/media/123');

    // Mock entity resolution and access check to allow processing to complete.
    $entity = $this->createMockTranslatableEntity('media', '123', TRUE, TRUE);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->any())
      ->method('load')
      ->willReturn($entity);
    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->willReturn($storage);
    $this->repository->expects($this->any())
      ->method('checkViewAccessEntity')
      ->willReturn(TRUE);

    $routeMatch = [
      '_route' => 'entity.media.canonical',
      '_entity' => $this->createMockEntity('media', '123'),
    ];
    $this->entityRender->setMockRouteMatch($routeMatch);

    $this->entityRender->callProcessInternalHref($target);

    // The langcode must be empty (from the path), not 'es' (from the source).
    $this->assertEmpty($target->getEntityLangcode());
  }

  /**
   * Tests that processInternalHref uses langcode from path when present.
   *
   * When the URL has a language prefix (e.g., /es/media/123), the target
   * langcode should be set to that prefix language.
   *
   * @covers ::processInternalHref
   */
  public function testProcessInternalHrefLangcodeFromPathWithPrefix() {
    $request = new Request();
    $this->requestStack->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $target = Target::create($this->requestStack);
    $target->setLinkType('internal');
    $target->setPath('/es/media/123');

    // Repository extracts 'es' from the path prefix.
    $this->repository->expects($this->once())
      ->method('getLangcodeFromPath')
      ->with('/es/media/123')
      ->willReturn('es');

    $this->repository->expects($this->once())
      ->method('getPathWithoutLangPrefix')
      ->with('/es/media/123')
      ->willReturn('/media/123');

    // Mock entity resolution and access check.
    $translation = $this->createMock(EntityInterface::class);
    $entity = $this->createMockTranslatableEntity('media', '123', TRUE, TRUE, $translation);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->any())
      ->method('load')
      ->willReturn($entity);
    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->willReturn($storage);
    $this->repository->expects($this->any())
      ->method('checkViewAccessEntity')
      ->willReturn(TRUE);

    $routeMatch = [
      '_route' => 'entity.media.canonical',
      '_entity' => $this->createMockEntity('media', '123'),
    ];
    $this->entityRender->setMockRouteMatch($routeMatch);

    $this->entityRender->callProcessInternalHref($target);

    // The langcode must be 'es' as extracted from the URL path.
    $this->assertEquals('es', $target->getEntityLangcode());
  }

  /**
   * Tests fallback langcode is used when URL has no language prefix.
   *
   * When a URL has no language prefix (e.g. /node-alias) and the source
   * entity provides a fallback langcode (e.g. 'de'), the target langcode
   * must be set to 'de' so redirect lookups can match the correct record
   * (issue #3570211).
   *
   * @covers ::processInternalHref
   */
  public function testProcessInternalHrefUsesFallbackLangcodeWhenNoPrefix() {
    $request = new Request();
    $this->requestStack->expects($this->once())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $target = Target::create($this->requestStack);
    $target->setLinkType('internal');
    $target->setPath('/node-1-de-alias');

    // No language prefix in URL so getLangcodeFromPath returns NULL.
    $this->repository->expects($this->once())
      ->method('getLangcodeFromPath')
      ->with('/node-1-de-alias')
      ->willReturn(NULL);

    $this->repository->expects($this->once())
      ->method('getPathWithoutLangPrefix')
      ->with('/node-1-de-alias')
      ->willReturn('/node-1-de-alias');

    // Mock entity resolution and access check to allow processing to complete.
    // Pass the entity itself as its own translation so getTranslation() works.
    $translation = $this->createMockEntity('node', '1');
    $entity = $this->createMockTranslatableEntity(
      'node', '1', TRUE, TRUE, $translation
    );
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->any())
      ->method('load')
      ->willReturn($entity);
    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->willReturn($storage);
    $this->repository->expects($this->any())
      ->method('checkViewAccessEntity')
      ->willReturn(TRUE);

    $routeMatch = [
      '_route' => 'entity.node.canonical',
      '_entity' => $this->createMockEntity('node', '1'),
    ];
    $this->entityRender->setMockRouteMatch($routeMatch);

    // Pass 'de' as the source entity's fallback langcode.
    $this->entityRender->callProcessInternalHref($target, 'de');

    // Langcode must be 'de' from the fallback, not NULL.
    $this->assertEquals('de', $target->getEntityLangcode());
  }

  /**
   * Tests that untranslated entity access falls back to default translation.
   *
   * When an entity does not have a translation in the langcode resolved for
   * the target, accessCheckTarget() must check access against the default
   * translation instead of denying outright. This prevents false
   * "access denied" for entities that only exist in their default language
   * but are otherwise accessible.
   *
   * @covers ::accessCheckTarget
   */
  public function testAccessCheckTargetUntranslatedEntityFallsBackToDefault() {
    $langcode = 'de';

    // Entity exists but has no 'de' translation.
    $entity = $this->createMockTranslatableEntity(
      'media',
      '5',
      TRUE,
      FALSE
    );

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())
      ->method('load')
      ->with('5')
      ->willReturn($entity);

    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('media')
      ->willReturn($storage);

    // Access check falls back to the entity's default translation (TRUE here).
    $this->repository->expects($this->once())
      ->method('checkViewAccessEntity')
      ->with($entity)
      ->willReturn(TRUE);

    $target = $this->createInternalTarget('media', '5', $langcode);

    $result = $this->entityRender->accessCheckTarget($target);

    $this->assertTrue($result);
  }

  /**
   * Tests entity without translation falls back to default translation check.
   *
   * When an entity does not have a translation in the requested langcode,
   * access is checked against the default translation rather than
   * returning FALSE outright. This prevents false "access denied" results
   * for entities that are accessible in their default language.
   *
   * @covers ::accessCheckTarget
   */
  public function testAccessCheckTargetWithoutTranslationFallsBackToDefault() {
    $langcode = 'es';

    $entity = $this->createMockTranslatableEntity(
      'node',
      '1',
      TRUE,
      FALSE
    );

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())
      ->method('load')
      ->with('1')
      ->willReturn($entity);

    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('node')
      ->willReturn($storage);

    // The default translation check must be called instead of denying.
    $this->repository->expects($this->once())
      ->method('checkViewAccessEntity')
      ->with($entity)
      ->willReturn(FALSE);

    $target = $this->createInternalTarget('node', '1', $langcode);

    $result = $this->entityRender->accessCheckTarget($target);

    $this->assertFalse($result);
  }

  /**
   * Tests media entity with translation checks the correct translation.
   *
   * Regression test: when the requested translation exists for a media
   * entity, access is checked against that translation (not the fallback).
   *
   * @covers ::accessCheckTarget
   */
  public function testAccessCheckTargetMediaWithTranslationChecksTranslation() {
    $langcode = 'es';

    $translation = $this->createMock(EntityInterface::class);

    $entity = $this->createMockTranslatableEntity(
      'media',
      '1',
      TRUE,
      TRUE,
      $translation
    );

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())
      ->method('load')
      ->with('1')
      ->willReturn($entity);

    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('media')
      ->willReturn($storage);

    $this->repository->expects($this->once())
      ->method('checkViewAccessEntity')
      ->with($translation)
      ->willReturn(TRUE);

    $target = $this->createInternalTarget('media', '1', $langcode);

    $result = $this->entityRender->accessCheckTarget($target);

    $this->assertTrue($result);
  }

  /**
   * Tests entity with matching translation checks access normally.
   *
   * Standard case: when the requested translation exists, access is
   * checked against that translation object.
   *
   * @covers ::accessCheckTarget
   */
  public function testAccessCheckTargetWithMatchingTranslationChecksAccess() {
    $langcode = 'fr';

    $translation = $this->createMock(EntityInterface::class);

    $entity = $this->createMockTranslatableEntity(
      'node',
      '42',
      TRUE,
      TRUE,
      $translation
    );

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())
      ->method('load')
      ->with('42')
      ->willReturn($entity);

    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('node')
      ->willReturn($storage);

    $this->repository->expects($this->once())
      ->method('checkViewAccessEntity')
      ->with($translation)
      ->willReturn(TRUE);

    $target = $this->createInternalTarget('node', '42', $langcode);

    $result = $this->entityRender->accessCheckTarget($target);

    $this->assertTrue($result);
  }

  /**
   * Creates a mock translatable entity.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $id
   *   The entity ID.
   * @param bool $is_translatable
   *   Whether the entity is translatable.
   * @param bool $has_translation
   *   Whether the entity has the requested translation.
   * @param \Drupal\Core\Entity\EntityInterface|null $translation
   *   The translation entity to return from getTranslation().
   * @param \Drupal\Core\Entity\EntityInterface|null $untranslated
   *   The untranslated entity to return from getUntranslated().
   *
   * @return \PHPUnit\Framework\MockObject\MockObject
   *   A mock implementing both EntityInterface and TranslatableInterface.
   */
  protected function createMockTranslatableEntity(
    string $entity_type,
    string $id,
    bool $is_translatable,
    bool $has_translation,
    ?EntityInterface $translation = NULL,
    ?EntityInterface $untranslated = NULL,
  ) {
    $entity = $this->getMockBuilder(TranslatableEntityStub::class)
      ->disableOriginalConstructor()
      ->getMock();

    $entity->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn($entity_type);

    $entity->expects($this->any())
      ->method('id')
      ->willReturn($id);

    $entity->expects($this->any())
      ->method('isTranslatable')
      ->willReturn($is_translatable);

    $entity->expects($this->any())
      ->method('hasTranslation')
      ->willReturn($has_translation);

    if ($translation !== NULL) {
      $entity->expects($this->any())
        ->method('getTranslation')
        ->willReturn($translation);
    }

    if ($untranslated !== NULL) {
      $entity->expects($this->any())
        ->method('getUntranslated')
        ->willReturn($untranslated);
    }

    return $entity;
  }

  /**
   * Creates an internal Target with entity type, ID, and langcode set.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $entity_id
   *   The entity ID.
   * @param string $langcode
   *   The langcode to request.
   *
   * @return \Drupal\entity_mesh\Target
   *   The configured target instance.
   */
  protected function createInternalTarget(
    string $entity_type,
    string $entity_id,
    string $langcode,
  ): Target {
    $target = new Target();
    $target->setLinkType('internal');
    $target->setEntityType($entity_type);
    $target->setEntityId($entity_id);
    $target->setEntityLangcode($langcode);
    return $target;
  }

  /**
   * Creates a mock entity.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $id
   *   The entity ID.
   *
   * @return \Drupal\Core\Entity\EntityInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The mock entity.
   */
  protected function createMockEntity($entity_type, $id) {
    $entity = $this->createMock(EntityInterface::class);
    $entity->expects($this->any())
      ->method('getEntityTypeId')
      ->willReturn($entity_type);
    $entity->expects($this->any())
      ->method('id')
      ->willReturn($id);
    return $entity;
  }

}

/**
 * Stub class implementing both EntityInterface and TranslatableInterface.
 *
 * Used as the base for mock translatable entities in tests.
 */
abstract class TranslatableEntityStub implements EntityInterface, TranslatableInterface {
}

/**
 * Testable version of EntityRender that allows mocking router service.
 */
class TestableEntityRender extends EntityRender {

  /**
   * Mock route match to return.
   *
   * @var array
   */
  protected $mockRouteMatch;

  /**
   * Exception to throw when matching routes.
   *
   * @var \Exception|null
   */
  protected $routeMatchException;

  /**
   * Sets the mock route match.
   *
   * @param array $routeMatch
   *   The route match to return.
   */
  public function setMockRouteMatch(array $routeMatch) {
    $this->mockRouteMatch = $routeMatch;
  }

  /**
   * Sets an exception to throw when matching routes.
   *
   * @param \Exception $exception
   *   The exception to throw.
   */
  public function setRouteMatchException(\Exception $exception) {
    $this->routeMatchException = $exception;
  }

  /**
   * Public wrapper for protected setDataTargetFromRoute method.
   *
   * @param \Drupal\entity_mesh\Target $target
   *   The target.
   */
  public function callSetDataTargetFromRoute($target) {
    $this->setDataTargetFromRoute($target);
  }

  /**
   * Public wrapper for protected processInternalHref method.
   *
   * @param \Drupal\entity_mesh\TargetInterface $target
   *   The target.
   * @param string|null $fallback_langcode
   *   Optional fallback langcode from the source entity.
   */
  public function callProcessInternalHref(
    $target,
    ?string $fallback_langcode = NULL,
  ) {
    $this->processInternalHref($target, $fallback_langcode);
  }

  /**
   * {@inheritdoc}
   */
  protected function setDataTargetFromRoute($target) {
    if (empty($target->getPath())) {
      return;
    }

    if ($this->routeMatchException) {
      $target->setSubcategory('broken-link');
      return;
    }

    $route_match = $this->mockRouteMatch;

    if (empty($route_match['_route'])) {
      $target->setSubcategory('broken-link');
      return;
    }

    $entity = $this->checkAndGetEntityFromEntityRoute($route_match);
    if ($entity instanceof EntityInterface) {
      $target->setEntityType($entity->getEntityTypeId());
      $target->setEntityId((string) $entity->id());
      return;
    }

    if (isset($route_match['view_id']) && isset($route_match['display_id'])) {
      $target->setEntityType('view');
      $target->setEntityId($route_match['view_id'] . '.' . $route_match['display_id']);
      return;
    }

    $route_parts = explode('.', $route_match['_route']);
    if (count($route_parts) > 1) {
      $entity = $route_parts[1];
      $target->setEntityType($entity);
      $target->setEntityId("");
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAndGetEntityFromEntityRoute(array $route_match): ?EntityInterface {
    return $route_match['_entity'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function setDataIfRedirection(string &$alias, TargetInterface $target): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function setDataTargetFromAliasIfExists(string $alias, TargetInterface $target): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function setDataTargetIfFileUrl($target) {
  }

  /**
   * {@inheritdoc}
   */
  protected function setBundleInTarget(TargetInterface $target) {
  }

}
