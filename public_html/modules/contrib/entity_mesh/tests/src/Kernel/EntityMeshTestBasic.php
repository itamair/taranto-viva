<?php

namespace Drupal\Tests\entity_mesh\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\file\Entity\File;
use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\MediaTypeInterface;
use Drupal\media\Entity\Media;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use org\bovigo\vfs\vfsStream;

/**
 * Entity mesh kernel basic.
 */
abstract class EntityMeshTestBasic extends KernelTestBase {

  use ContentTypeCreationTrait;
  use MediaTypeCreationTrait;
  use UserCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array<string>
   */
  protected static $modules = [
    'system',
    'node',
    'user',
    'field',
    'filter',
    'text',
    'language',
    'entity_mesh',
    'path_alias',
    'file',
    'image',
    'media',
  ];

  /**
   * File Media Type.
   *
   * @var \Drupal\media\MediaTypeInterface
   */
  protected $fileMediaType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install the necessary schemas.
    $this->installEntitySchema('configurable_language');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('path_alias');
    $this->installSchema('file', 'file_usage');
    $this->installSchema('entity_mesh', ['entity_mesh', 'entity_mesh_tracker']);
    $this->installConfig(['filter', 'node', 'file', 'media', 'system', 'language', 'entity_mesh']);
    $this->installSchema('node', ['node_access']);

    $this->createContentType(['type' => 'page', 'name' => 'Page']);

    // Create a file media type.
    $this->fileMediaType = $this->createMediaType('file', ['id' => 'document', 'label' => 'Document']);

    $config = $this->config('language.negotiation');
    $config->set('url.prefixes', ['en' => 'en'])
      ->save();

    // Enable the body field in the default view mode.
    $this->container->get('entity_display.repository')
      ->getViewDisplay('node', 'page', 'full')
      ->setComponent('body', [
        // Show label above the body content.
        'label' => 'above',
        // Render as basic text.
        'type' => 'text_default',
      ])
      ->save();

    $filter_format = FilterFormat::load('basic_html');
    if (!$filter_format) {
      $filter_format = FilterFormat::create([
        'format' => 'basic_html',
        'name' => 'Basic HTML',
        'filters' => [],
      ]);
      $filter_format->save();
    }

    if (!Role::load(AccountInterface::ANONYMOUS_ROLE)) {
      Role::create(['id' => AccountInterface::ANONYMOUS_ROLE, 'label' => 'Anonymous user'])->save();
    }

    $this->grantPermissions(Role::load(AccountInterface::ANONYMOUS_ROLE), [
      'access content',
      'view media',
    ]);

    // Set entity_mesh to synchronous mode by default for tests.
    // This ensures backward compatibility with existing tests that expect
    // immediate processing.
    $config = $this->config('entity_mesh.settings');
    $config->set('processing_mode', 'synchronous');
    // High limit to process everything.
    $config->set('synchronous_limit', 9999);
    $config->save();

  }

  /**
   * Helper to generate a media item.
   *
   * @param string $filename
   *   String filename with extension.
   * @param \Drupal\media\MediaTypeInterface $media_type
   *   The media type.
   * @param int $mid
   *   The media ID.
   *
   * @return \Drupal\media\Entity\Media
   *   A media item.
   */
  protected function generateMedia($filename, MediaTypeInterface $media_type, int $mid) {
    vfsStream::setup('drupal_root');
    vfsStream::create([
      'sites' => [
        'default' => [
          'files' => [
            $filename => str_repeat('a', 3000),
          ],
        ],
      ],
    ]);

    $file = File::create([
      'uri' => 'public://' . $filename,
    ]);
    $file->setPermanent();
    $file->save();

    return Media::create([
      'bundle' => $media_type->id(),
      'name' => $filename,
      'mid' => $mid,
      'field_media_file' => [
        'target_id' => $file->id(),
      ],
    ]);
  }

  /**
   * Helper to the url from uri.
   *
   * @param string $mid
   *   Media ID.
   *
   * @return string
   *   The url.
   */
  public static function getUrlFromMedia($mid) {
    $media = Media::load($mid);
    $uri = $media->get('field_media_file')->entity->getFileUri();
    return \Drupal::service('file_url_generator')->generateString($uri);
  }

}
