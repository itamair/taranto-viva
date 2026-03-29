<?php

declare(strict_types=1);

namespace Drupal\Tests\graphql_compose\Functional\Core;

use Drupal\Tests\graphql_compose\Functional\GraphQLComposeBrowserTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;

/**
 * Tests specific to GraphQL Compose entity type: Media.
 *
 * @group graphql_compose
 */
class EntityMediaTest extends GraphQLComposeBrowserTestBase {

  use MediaTypeCreationTrait;

  /**
   * The test media.
   *
   * @var \Drupal\media\MediaInterface
   */
  protected MediaInterface $media;

  /**
   * The test image.
   *
   * @var \Drupal\file\FileInterface
   */
  protected FileInterface $file;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'media',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createMediaType('image', ['id' => 'test']);

    // Create a file.
    $this->file = File::create();
    $this->file->setFileUri('core/misc/druplicon.png');
    $this->file->setFilename(basename($this->file->getFileUri()));

    $this->file->setPermanent();
    $this->file->save();

    $this->media = Media::create([
      'bundle' => 'test',
      'name' => 'Banana bread',
      'status' => TRUE,
      'field_media_image' => [
        [
          'target_id' => $this->file->id(),
          'alt' => 'default alt',
          'title' => 'default title',
        ],
      ],
    ]);
    $this->media->save();

    $this->setEntityConfig('media', 'test', [
      'enabled' => TRUE,
      'query_load_enabled' => TRUE,
    ]);

    $this->setFieldConfig('media', 'test', 'field_media_image', [
      'enabled' => TRUE,
    ]);
  }

  /**
   * Test load entity by id.
   */
  public function testMediaLoadByUuid(): void {
    $query = <<<GQL
      query {
        media(id: "{$this->media->uuid()}") {
          ... on MediaTest {
            id
            status
            name
            created {
              timestamp
            }
            mediaImage {
              url
            }
          }
        }
      }
    GQL;

    $content = $this->executeQuery($query);

    $media = $content['data']['media'];

    $this->assertEquals($this->media->uuid(), $media['id']);

    $this->assertNotEmpty($media['mediaImage']['url']);
    $this->assertTrue($media['status']);
    $this->assertEquals('Banana bread', $media['name']);
    $this->assertEquals($this->media->getCreatedTime(), $media['created']['timestamp']);
  }

}
