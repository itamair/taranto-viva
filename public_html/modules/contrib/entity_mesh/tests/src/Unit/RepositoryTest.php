<?php

namespace Drupal\Tests\entity_mesh\Unit;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\entity_mesh\DummyAccount;
use Drupal\entity_mesh\Repository;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the Repository class.
 *
 * @group entity_mesh
 * @coversDefaultClass \Drupal\entity_mesh\Repository
 */
class RepositoryTest extends UnitTestCase {

  /**
   * The repository service under test.
   *
   * @var \Drupal\entity_mesh\Repository
   */
  protected $repository;

  /**
   * The mocked config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * The mocked database connection.
   *
   * @var \Drupal\Core\Database\Connection|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $database;

  /**
   * The mocked logger.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $logger;

  /**
   * The mocked request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $requestStack;

  /**
   * The mocked entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The mocked entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityFieldManager;

  /**
   * The mocked file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $fileUrlGenerator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->database = $this->createMock(Connection::class);
    $this->logger = $this->createMock(LoggerInterface::class);
    $this->requestStack = $this->createMock(RequestStack::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $this->fileUrlGenerator = $this->createMock(FileUrlGeneratorInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);

    // Mock the language.negotiation config for the constructor.
    $languageConfig = $this->createMock(Config::class);
    $languageConfig->expects($this->any())
      ->method('get')
      ->with('url.prefixes')
      ->willReturn([]);

    $this->configFactory->expects($this->any())
      ->method('get')
      ->willReturnMap([
        ['language.negotiation', $languageConfig],
      ]);

    $this->repository = new Repository(
      $this->database,
      $this->logger,
      $this->requestStack,
      $this->entityTypeManager,
      $this->entityFieldManager,
      $this->configFactory,
      $this->fileUrlGenerator
    );
  }

  /**
   * Tests getMeshAccount with anonymous account type.
   *
   * @covers ::getMeshAccount
   */
  public function testGetMeshAccountWithAnonymousType() {
    // Mock entity_mesh.settings config.
    $settingsConfig = $this->createMock(Config::class);
    $settingsConfig->expects($this->once())
      ->method('get')
      ->with('analyzer_account')
      ->willReturn(['type' => 'anonymous', 'roles' => NULL, 'user_id' => NULL]);

    // Mock language.negotiation config for constructor.
    $languageConfig = $this->createMock(Config::class);
    $languageConfig->expects($this->any())
      ->method('get')
      ->with('url.prefixes')
      ->willReturn([]);

    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->configFactory->expects($this->any())
      ->method('get')
      ->willReturnMap([
        ['entity_mesh.settings', $settingsConfig],
        ['language.negotiation', $languageConfig],
      ]);

    // Create a new repository instance with the mocked config factory.
    $repository = new Repository(
      $this->database,
      $this->logger,
      $this->requestStack,
      $this->entityTypeManager,
      $this->entityFieldManager,
      $this->configFactory,
      $this->fileUrlGenerator
    );

    $account = $repository->getMeshAccount();

    $this->assertInstanceOf(DummyAccount::class, $account);
    $this->assertTrue($account->isAnonymous());
    $this->assertContains('anonymous', $account->getRoles());
  }

  /**
   * Tests getMeshAccount with authenticated account type.
   *
   * @covers ::getMeshAccount
   */
  public function testGetMeshAccountWithAuthenticatedType() {
    $additionalRoles = ['editor', 'administrator'];

    // Mock entity_mesh.settings config.
    $settingsConfig = $this->createMock(Config::class);
    $settingsConfig->expects($this->once())
      ->method('get')
      ->with('analyzer_account')
      ->willReturn(['type' => 'authenticated', 'roles' => $additionalRoles, 'user_id' => NULL]);

    // Mock language.negotiation config for constructor.
    $languageConfig = $this->createMock(Config::class);
    $languageConfig->expects($this->any())
      ->method('get')
      ->with('url.prefixes')
      ->willReturn([]);

    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->configFactory->expects($this->any())
      ->method('get')
      ->willReturnMap([
        ['entity_mesh.settings', $settingsConfig],
        ['language.negotiation', $languageConfig],
      ]);

    // Create a new repository instance with the mocked config factory.
    $repository = new Repository(
      $this->database,
      $this->logger,
      $this->requestStack,
      $this->entityTypeManager,
      $this->entityFieldManager,
      $this->configFactory,
      $this->fileUrlGenerator
    );

    $account = $repository->getMeshAccount();

    $this->assertInstanceOf(DummyAccount::class, $account);
    $this->assertTrue($account->isAuthenticated());
    $this->assertFalse($account->isAnonymous());
    $expectedRoles = ['authenticated', 'editor', 'administrator'];
    $this->assertEquals($expectedRoles, $account->getRoles());
  }

  /**
   * Tests that getMeshAccount uses instance caching.
   *
   * @covers ::getMeshAccount
   */
  public function testGetMeshAccountCaching() {
    // Mock entity_mesh.settings config.
    $settingsConfig = $this->createMock(Config::class);
    // Expect get() to be called only once due to instance caching.
    $settingsConfig->expects($this->once())
      ->method('get')
      ->with('analyzer_account')
      ->willReturn(['type' => 'authenticated', 'roles' => [], 'user_id' => NULL]);

    // Mock language.negotiation config for constructor.
    $languageConfig = $this->createMock(Config::class);
    $languageConfig->expects($this->any())
      ->method('get')
      ->with('url.prefixes')
      ->willReturn([]);

    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->configFactory->expects($this->any())
      ->method('get')
      ->willReturnMap([
        ['entity_mesh.settings', $settingsConfig],
        ['language.negotiation', $languageConfig],
      ]);

    // Create a new repository instance with the mocked config factory.
    $repository = new Repository(
      $this->database,
      $this->logger,
      $this->requestStack,
      $this->entityTypeManager,
      $this->entityFieldManager,
      $this->configFactory,
      $this->fileUrlGenerator
    );

    // Call getMeshAccount multiple times.
    $account1 = $repository->getMeshAccount();
    $account2 = $repository->getMeshAccount();
    $account3 = $repository->getMeshAccount();

    // All calls should return the same instance due to instance caching.
    $this->assertSame($account1, $account2);
    $this->assertSame($account2, $account3);
  }

  /**
   * Tests getMeshAccount with user account type.
   *
   * @covers ::getMeshAccount
   */
  public function testGetMeshAccountWithUserType() {
    $userId = 123;
    $userRoles = ['authenticated', 'editor'];

    // Mock user entity.
    $user = $this->createMock(UserInterface::class);
    $user->expects($this->once())
      ->method('getRoles')
      ->willReturn($userRoles);

    // Mock user storage.
    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->expects($this->once())
      ->method('load')
      ->with($userId)
      ->willReturn($user);

    // Mock entity type manager.
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('user')
      ->willReturn($userStorage);

    // Mock entity_mesh.settings config.
    $settingsConfig = $this->createMock(Config::class);
    $settingsConfig->expects($this->once())
      ->method('get')
      ->with('analyzer_account')
      ->willReturn(['type' => 'user', 'roles' => NULL, 'user_id' => $userId]);

    // Mock language.negotiation config for constructor.
    $languageConfig = $this->createMock(Config::class);
    $languageConfig->expects($this->any())
      ->method('get')
      ->with('url.prefixes')
      ->willReturn([]);

    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->configFactory->expects($this->any())
      ->method('get')
      ->willReturnMap([
        ['entity_mesh.settings', $settingsConfig],
        ['language.negotiation', $languageConfig],
      ]);

    // Create a new repository instance with the mocked dependencies.
    $repository = new Repository(
      $this->database,
      $this->logger,
      $this->requestStack,
      $entityTypeManager,
      $this->entityFieldManager,
      $this->configFactory,
      $this->fileUrlGenerator
    );

    $account = $repository->getMeshAccount();

    $this->assertInstanceOf(DummyAccount::class, $account);
    $this->assertTrue($account->isAuthenticated());
    $this->assertFalse($account->isAnonymous());
    $this->assertEquals($userRoles, $account->getRoles());
  }

  /**
   * Tests parseImageStyleUrl with valid public image style URL.
   *
   * @covers ::parseImageStyleUrl
   */
  public function testParseImageStyleUrlWithPublicScheme() {
    $path = 'sites/default/files/styles/thumbnail/public/image.jpg';
    $result = $this->repository->parseImageStyleUrl($path);

    $this->assertIsArray($result);
    $this->assertEquals('thumbnail', $result['style']);
    $this->assertEquals('public', $result['scheme']);
    $this->assertEquals('image.jpg', $result['path']);
  }

  /**
   * Tests parseImageStyleUrl with valid private image style URL.
   *
   * @covers ::parseImageStyleUrl
   */
  public function testParseImageStyleUrlWithPrivateScheme() {
    $path = 'sites/default/files/styles/large/private/documents/report.pdf';
    $result = $this->repository->parseImageStyleUrl($path);

    $this->assertIsArray($result);
    $this->assertEquals('large', $result['style']);
    $this->assertEquals('private', $result['scheme']);
    $this->assertEquals('documents/report.pdf', $result['path']);
  }

  /**
   * Tests parseImageStyleUrl with nested directory structure.
   *
   * @covers ::parseImageStyleUrl
   */
  public function testParseImageStyleUrlWithNestedPath() {
    $path = 'sites/default/files/styles/medium/public/2024-01/photos/vacation.jpg';
    $result = $this->repository->parseImageStyleUrl($path);

    $this->assertIsArray($result);
    $this->assertEquals('medium', $result['style']);
    $this->assertEquals('public', $result['scheme']);
    $this->assertEquals('2024-01/photos/vacation.jpg', $result['path']);
  }

  /**
   * Tests parseImageStyleUrl with invalid URL (not an image style).
   *
   * @covers ::parseImageStyleUrl
   */
  public function testParseImageStyleUrlWithInvalidUrl() {
    $path = 'sites/default/files/image.jpg';
    $result = $this->repository->parseImageStyleUrl($path);

    $this->assertNull($result);
  }

  /**
   * Tests parseImageStyleUrl with leading slash.
   *
   * @covers ::parseImageStyleUrl
   */
  public function testParseImageStyleUrlWithLeadingSlash() {
    $path = '/sites/default/files/styles/thumbnail/public/image.jpg';
    $result = $this->repository->parseImageStyleUrl($path);

    $this->assertIsArray($result);
    $this->assertEquals('thumbnail', $result['style']);
    $this->assertEquals('public', $result['scheme']);
    $this->assertEquals('image.jpg', $result['path']);
  }

  /**
   * Data provider for testGetOriginalFileUriFromStyleUrl.
   *
   * @return array
   *   Test cases with path and expected URI.
   */
  public static function providerGetOriginalFileUriFromStyleUrl() {
    return [
      // Single extension files - should remain unchanged.
      'single extension jpg' => [
        'sites/default/files/styles/thumbnail/public/test_file.jpg',
        'public://test_file.jpg',
      ],
      'single extension png' => [
        'sites/default/files/styles/large/public/photo.png',
        'public://photo.png',
      ],
      'single extension gif in subdirectory' => [
        'sites/default/files/styles/medium/public/2024-01/animation.gif',
        'public://2024-01/animation.gif',
      ],

      // Double extension files - should remove last extension.
      'double extension jpg.webp' => [
        'sites/default/files/styles/thumbnail/public/test_file.jpg.webp',
        'public://test_file.jpg',
      ],
      'double extension png.avif' => [
        'sites/default/files/styles/large/public/photo.png.avif',
        'public://photo.png',
      ],
      'double extension jpeg.webp' => [
        'sites/default/files/styles/medium/public/image.jpeg.webp',
        'public://image.jpeg',
      ],
      'double extension gif.webp' => [
        'sites/default/files/styles/thumbnail/public/banner.gif.webp',
        'public://banner.gif',
      ],

      // Files with dots in the name but single image extension.
      'dots in name single extension' => [
        'sites/default/files/styles/large/public/file.backup.2024.jpg',
        'public://file.backup.2024.jpg',
      ],
      'dots in name with number' => [
        'sites/default/files/styles/medium/public/image.v2.final.png',
        'public://image.v2.final.png',
      ],

      // Files with dots in the name AND double image extensions.
      'dots in name double extension' => [
        'sites/default/files/styles/thumbnail/public/file.backup.2024.jpg.webp',
        'public://file.backup.2024.jpg',
      ],
      'dots in name double extension png' => [
        'sites/default/files/styles/large/public/image.v2.final.png.avif',
        'public://image.v2.final.png',
      ],

      // Nested directory structures.
      'nested path single extension' => [
        'sites/default/files/styles/thumbnail/public/2024/11/photos/vacation.jpg',
        'public://2024/11/photos/vacation.jpg',
      ],
      'nested path double extension' => [
        'sites/default/files/styles/large/public/2024/11/photos/vacation.jpg.webp',
        'public://2024/11/photos/vacation.jpg',
      ],

      // Private scheme.
      'private scheme single extension' => [
        'sites/default/files/styles/thumbnail/private/document.pdf',
        'private://document.pdf',
      ],
      'private scheme double extension' => [
        'sites/default/files/styles/large/private/report.png.webp',
        'private://report.png',
      ],
      'private scheme with nested path' => [
        'sites/default/files/styles/medium/private/2024/reports/annual.jpg.avif',
        'private://2024/reports/annual.jpg',
      ],

      // Edge cases with various image formats.
      'bmp to webp' => [
        'sites/default/files/styles/thumbnail/public/image.bmp.webp',
        'public://image.bmp',
      ],
      'tiff to avif' => [
        'sites/default/files/styles/large/public/scan.tiff.avif',
        'public://scan.tiff',
      ],
      'svg single extension' => [
        'sites/default/files/styles/medium/public/logo.svg',
        'public://logo.svg',
      ],

      // Files with uppercase extensions.
      'uppercase JPG single' => [
        'sites/default/files/styles/thumbnail/public/photo.JPG',
        'public://photo.JPG',
      ],
      'uppercase jpg to webp' => [
        'sites/default/files/styles/large/public/photo.JPG.webp',
        'public://photo.JPG',
      ],
      'mixed case PNG.AVIF' => [
        'sites/default/files/styles/medium/public/image.PNG.AVIF',
        'public://image.PNG',
      ],

      // Root level files (no subdirectories).
      'root level single extension' => [
        'sites/default/files/styles/thumbnail/public/favicon.ico',
        'public://favicon.ico',
      ],
      'root level double extension' => [
        'sites/default/files/styles/large/public/header.png.webp',
        'public://header.png',
      ],

      // Non-image double extensions should NOT be stripped.
      'non-image double extension' => [
        'sites/default/files/styles/thumbnail/public/file.txt.jpg',
        'public://file.txt.jpg',
      ],
    ];
  }

  /**
   * Tests getOriginalFileUriFromStyleUrl with various scenarios.
   *
   * @param string $path
   *   The image style URL path.
   * @param string $expected
   *   The expected original file URI.
   *
   * @dataProvider providerGetOriginalFileUriFromStyleUrl
   * @covers ::getOriginalFileUriFromStyleUrl
   * @covers ::reconstructOriginalPath
   */
  public function testGetOriginalFileUriFromStyleUrl($path, $expected) {
    $result = $this->repository->getOriginalFileUriFromStyleUrl($path);
    $this->assertEquals($expected, $result);
  }

  /**
   * Tests getOriginalFileUriFromStyleUrl with invalid URL.
   *
   * @covers ::getOriginalFileUriFromStyleUrl
   */
  public function testGetOriginalFileUriFromStyleUrlWithInvalidUrl() {
    $path = 'sites/default/files/image.jpg';
    $result = $this->repository->getOriginalFileUriFromStyleUrl($path);

    $this->assertNull($result);
  }

  /**
   * Tests that getOriginalFileUriFromStyleUrl uses static caching.
   *
   * @covers ::getOriginalFileUriFromStyleUrl
   */
  public function testGetOriginalFileUriFromStyleUrlCaching() {
    $path = 'sites/default/files/styles/thumbnail/public/cached.jpg.webp';

    // Call the method multiple times with the same path.
    $result1 = $this->repository->getOriginalFileUriFromStyleUrl($path);
    $result2 = $this->repository->getOriginalFileUriFromStyleUrl($path);
    $result3 = $this->repository->getOriginalFileUriFromStyleUrl($path);

    // All should return the same result.
    $this->assertEquals('public://cached.jpg', $result1);
    $this->assertEquals($result1, $result2);
    $this->assertEquals($result2, $result3);
  }

  /**
   * Tests getOriginalFileUriFromStyleUrl with complex filenames.
   *
   * @covers ::getOriginalFileUriFromStyleUrl
   * @covers ::reconstructOriginalPath
   */
  public function testGetOriginalFileUriFromStyleUrlWithComplexFilenames() {
    // File with multiple dots but only one image extension.
    $path1 = 'sites/default/files/styles/thumbnail/public/my.file.name.with.dots.jpg';
    $result1 = $this->repository->getOriginalFileUriFromStyleUrl($path1);
    $this->assertEquals('public://my.file.name.with.dots.jpg', $result1);

    // Same file with double extension.
    $path2 = 'sites/default/files/styles/thumbnail/public/my.file.name.with.dots.jpg.webp';
    $result2 = $this->repository->getOriginalFileUriFromStyleUrl($path2);
    $this->assertEquals('public://my.file.name.with.dots.jpg', $result2);

    // File with numbers and special characters.
    $path3 = 'sites/default/files/styles/large/public/IMG_20241120_143022.jpg.avif';
    $result3 = $this->repository->getOriginalFileUriFromStyleUrl($path3);
    $this->assertEquals('public://IMG_20241120_143022.jpg', $result3);
  }

  /**
   * Tests single-extension webp file with database lookup returning result.
   *
   * @covers ::getOriginalFileUriFromStyleUrl
   * @covers ::reconstructOriginalPath
   * @covers ::queryOriginalFileUri
   */
  public function testGetOriginalFileUriFromStyleUrlWithSingleWebpDbFound() {
    // Mock the database query to return a result.
    $statement = $this->createMock(StatementInterface::class);
    $statement->expects($this->once())
      ->method('fetchField')
      ->willReturn('public://test_file.jpg');

    $query = $this->createMock(SelectInterface::class);
    $query->expects($this->once())
      ->method('fields')
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('condition')
      ->with('uri', 'public://test_file%', 'LIKE')
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('range')
      ->with(0, 1)
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('execute')
      ->willReturn($statement);

    $this->database->expects($this->once())
      ->method('select')
      ->with('file_managed', 'fm')
      ->willReturn($query);
    $this->database->expects($this->once())
      ->method('escapeLike')
      ->with('public://test_file')
      ->willReturn('public://test_file');

    $path = 'sites/default/files/styles/large/public/test_file.webp';
    $result = $this->repository->getOriginalFileUriFromStyleUrl($path);

    $this->assertEquals('public://test_file.jpg', $result);
  }

  /**
   * Tests single-extension avif file with database lookup returning result.
   *
   * @covers ::getOriginalFileUriFromStyleUrl
   * @covers ::reconstructOriginalPath
   * @covers ::queryOriginalFileUri
   */
  public function testGetOriginalFileUriFromStyleUrlWithSingleAvifDbFound() {
    // Mock the database query to return a result.
    $statement = $this->createMock(StatementInterface::class);
    $statement->expects($this->once())
      ->method('fetchField')
      ->willReturn('public://photo.png');

    $query = $this->createMock(SelectInterface::class);
    $query->expects($this->once())
      ->method('fields')
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('condition')
      ->with('uri', 'public://photo%', 'LIKE')
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('range')
      ->with(0, 1)
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('execute')
      ->willReturn($statement);

    $this->database->expects($this->once())
      ->method('select')
      ->with('file_managed', 'fm')
      ->willReturn($query);
    $this->database->expects($this->once())
      ->method('escapeLike')
      ->with('public://photo')
      ->willReturn('public://photo');

    $path = 'sites/default/files/styles/thumbnail/public/photo.avif';
    $result = $this->repository->getOriginalFileUriFromStyleUrl($path);

    $this->assertEquals('public://photo.png', $result);
  }

  /**
   * Tests single-extension webp with database lookup returning no result.
   *
   * Falls back to constructed URI.
   *
   * @covers ::getOriginalFileUriFromStyleUrl
   * @covers ::reconstructOriginalPath
   * @covers ::queryOriginalFileUri
   */
  public function testGetOriginalFileUriFromStyleUrlWithSingleWebpDbNotFound() {
    // Mock the database query to return null (no result).
    $statement = $this->createMock(StatementInterface::class);
    $statement->expects($this->once())
      ->method('fetchField')
      ->willReturn(FALSE);

    $query = $this->createMock(SelectInterface::class);
    $query->expects($this->once())
      ->method('fields')
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('condition')
      ->with('uri', 'public://missing%', 'LIKE')
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('range')
      ->with(0, 1)
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('execute')
      ->willReturn($statement);

    $this->database->expects($this->once())
      ->method('select')
      ->with('file_managed', 'fm')
      ->willReturn($query);
    $this->database->expects($this->once())
      ->method('escapeLike')
      ->with('public://missing')
      ->willReturn('public://missing');

    $path = 'sites/default/files/styles/large/public/missing.webp';
    $result = $this->repository->getOriginalFileUriFromStyleUrl($path);

    // Should fall back to the constructed URI.
    $this->assertEquals('public://missing.webp', $result);
  }

  /**
   * Tests double-extension webp file does NOT trigger database lookup.
   *
   * @covers ::getOriginalFileUriFromStyleUrl
   * @covers ::reconstructOriginalPath
   */
  public function testGetOriginalFileUriFromStyleUrlWithDoubleWebpNoDb() {
    // Database should NOT be queried for double-extension files.
    $this->database->expects($this->never())
      ->method('select');

    $path = 'sites/default/files/styles/large/public/test_file.jpg.webp';
    $result = $this->repository->getOriginalFileUriFromStyleUrl($path);

    $this->assertEquals('public://test_file.jpg', $result);
  }

  /**
   * Tests double-extension avif file does NOT trigger database lookup.
   *
   * @covers ::getOriginalFileUriFromStyleUrl
   * @covers ::reconstructOriginalPath
   */
  public function testGetOriginalFileUriFromStyleUrlWithDoubleAvifNoDb() {
    // Database should NOT be queried for double-extension files.
    $this->database->expects($this->never())
      ->method('select');

    $path = 'sites/default/files/styles/thumbnail/public/photo.png.avif';
    $result = $this->repository->getOriginalFileUriFromStyleUrl($path);

    $this->assertEquals('public://photo.png', $result);
  }

  /**
   * Tests single-extension non-webp/avif file does NOT trigger database lookup.
   *
   * @covers ::getOriginalFileUriFromStyleUrl
   * @covers ::reconstructOriginalPath
   */
  public function testGetOriginalFileUriFromStyleUrlWithSingleJpgNoDb() {
    // Database should NOT be queried for non-webp/avif files.
    $this->database->expects($this->never())
      ->method('select');

    $path = 'sites/default/files/styles/large/public/logo.jpg';
    $result = $this->repository->getOriginalFileUriFromStyleUrl($path);

    $this->assertEquals('public://logo.jpg', $result);
  }

  /**
   * Tests single-extension webp with nested path and database lookup.
   *
   * @covers ::getOriginalFileUriFromStyleUrl
   * @covers ::reconstructOriginalPath
   * @covers ::queryOriginalFileUri
   */
  public function testGetOriginalFileUriFromStyleUrlWithNestedWebpDbFound() {
    // Mock the database query to return a result.
    $statement = $this->createMock(StatementInterface::class);
    $statement->expects($this->once())
      ->method('fetchField')
      ->willReturn('public://2024-01/test_file.jpg');

    $query = $this->createMock(SelectInterface::class);
    $query->expects($this->once())
      ->method('fields')
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('condition')
      ->with('uri', 'public://2024-01/test_file%', 'LIKE')
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('range')
      ->with(0, 1)
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('execute')
      ->willReturn($statement);

    $this->database->expects($this->once())
      ->method('select')
      ->with('file_managed', 'fm')
      ->willReturn($query);
    $this->database->expects($this->once())
      ->method('escapeLike')
      ->with('public://2024-01/test_file')
      ->willReturn('public://2024-01/test_file');

    $path = 'sites/default/files/styles/large/public/2024-01/test_file.webp';
    $result = $this->repository->getOriginalFileUriFromStyleUrl($path);

    $this->assertEquals('public://2024-01/test_file.jpg', $result);
  }

  /**
   * Tests database lookup caching for single-extension webp files.
   *
   * @covers ::getOriginalFileUriFromStyleUrl
   * @covers ::reconstructOriginalPath
   * @covers ::queryOriginalFileUri
   */
  public function testGetOriginalFileUriFromStyleUrlWithWebpDbCaching() {
    // Mock the database query to return a result.
    $statement = $this->createMock(StatementInterface::class);
    $statement->expects($this->once())
      ->method('fetchField')
      ->willReturn('public://cached.jpg');

    $query = $this->createMock(SelectInterface::class);
    $query->expects($this->once())
      ->method('fields')
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('condition')
      ->with('uri', 'public://cached%', 'LIKE')
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('range')
      ->with(0, 1)
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('execute')
      ->willReturn($statement);

    // Database select should only be called once due to caching.
    $this->database->expects($this->once())
      ->method('select')
      ->with('file_managed', 'fm')
      ->willReturn($query);
    $this->database->expects($this->once())
      ->method('escapeLike')
      ->with('public://cached')
      ->willReturn('public://cached');

    $path = 'sites/default/files/styles/large/public/cached.webp';

    // Call multiple times - should only query database once.
    $result1 = $this->repository->getOriginalFileUriFromStyleUrl($path);
    $result2 = $this->repository->getOriginalFileUriFromStyleUrl($path);
    $result3 = $this->repository->getOriginalFileUriFromStyleUrl($path);

    $this->assertEquals('public://cached.jpg', $result1);
    $this->assertEquals('public://cached.jpg', $result2);
    $this->assertEquals('public://cached.jpg', $result3);
  }

  /**
   * Tests single-extension webp with private scheme and database lookup.
   *
   * @covers ::getOriginalFileUriFromStyleUrl
   * @covers ::reconstructOriginalPath
   * @covers ::queryOriginalFileUri
   */
  public function testGetOriginalFileUriFromStyleUrlWithPrivateWebpDbFound() {
    // Mock the database query to return a result.
    $statement = $this->createMock(StatementInterface::class);
    $statement->expects($this->once())
      ->method('fetchField')
      ->willReturn('private://document.png');

    $query = $this->createMock(SelectInterface::class);
    $query->expects($this->once())
      ->method('fields')
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('condition')
      ->with('uri', 'private://document%', 'LIKE')
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('range')
      ->with(0, 1)
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('execute')
      ->willReturn($statement);

    $this->database->expects($this->once())
      ->method('select')
      ->with('file_managed', 'fm')
      ->willReturn($query);
    $this->database->expects($this->once())
      ->method('escapeLike')
      ->with('private://document')
      ->willReturn('private://document');

    $path = 'sites/default/files/styles/large/private/document.webp';
    $result = $this->repository->getOriginalFileUriFromStyleUrl($path);

    $this->assertEquals('private://document.png', $result);
  }

  /**
   * Tests database query error handling for single-extension webp.
   *
   * @covers ::getOriginalFileUriFromStyleUrl
   * @covers ::reconstructOriginalPath
   * @covers ::queryOriginalFileUri
   */
  public function testGetOriginalFileUriFromStyleUrlWithWebpDbError() {
    // Mock the query to throw an exception on execute.
    $query = $this->createMock(SelectInterface::class);
    $query->expects($this->once())
      ->method('fields')
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('condition')
      ->with('uri', 'public://error%', 'LIKE')
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('range')
      ->with(0, 1)
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('execute')
      ->willThrowException(new \Exception('Database error'));

    $this->database->expects($this->once())
      ->method('select')
      ->with('file_managed', 'fm')
      ->willReturn($query);
    $this->database->expects($this->once())
      ->method('escapeLike')
      ->with('public://error')
      ->willReturn('public://error');

    // Logger should be called with error.
    $this->logger->expects($this->once())
      ->method('error')
      ->with('Error querying file URI: @message', ['@message' => 'Database error']);

    $path = 'sites/default/files/styles/large/public/error.webp';
    $result = $this->repository->getOriginalFileUriFromStyleUrl($path);

    // Should fall back to constructed URI on error.
    $this->assertEquals('public://error.webp', $result);
  }

}
