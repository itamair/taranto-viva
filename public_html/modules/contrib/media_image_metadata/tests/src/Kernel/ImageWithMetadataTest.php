<?php

declare(strict_types=1);

namespace Drupal\Tests\media_image_metadata\Kernel;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileExists;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\media_image_metadata\Service\ImageMetadataHelper;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the ImageWithMetadata media source plugin.
 */
#[CoversClass(\Drupal\media_image_metadata\Plugin\media\Source\ImageWithMetadata::class)]
#[Group('media_image_metadata')]
class ImageWithMetadataTest extends KernelTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'image',
    'media',
    'media_image_metadata',
  ];

  /**
   * The media type entity.
   *
   * @var \Drupal\media\Entity\MediaType
   */
  protected MediaType $mediaType;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The image metadata helper service.
   *
   * @var \Drupal\media_image_metadata\Service\ImageMetadataHelper
   */
  protected ImageMetadataHelper $metadataHelper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('file', 'file_usage');
    $this->installConfig(['system', 'field', 'file', 'image', 'media']);

    $this->fileSystem = $this->container->get('file_system');
    $this->metadataHelper = $this->container->get(ImageMetadataHelper::class);

    // Create a media type that uses our enhanced image source.
    $this->mediaType = $this->createMediaType('image', [
      'id' => 'image_with_metadata',
      'label' => 'Image with Metadata',
      'source_configuration' => [
        'source_field' => 'field_media_image',
        'extract_metadata' => 1,
      ],
    ]);
  }

  /**
   * Tests media source plugin instantiation and configuration.
   */
  public function testMediaSourceConfiguration(): void {
    $source = $this->mediaType->getSource();

    $this->assertInstanceOf('Drupal\media_image_metadata\Plugin\media\Source\ImageWithMetadata', $source);

    $default_config = $source->defaultConfiguration();
    $this->assertArrayHasKey('extract_metadata', $default_config);
    $this->assertEquals(0, $default_config['extract_metadata']);

    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $configuration_form = $source->buildConfigurationForm($form, $form_state);

    $this->assertArrayHasKey('extract_metadata', $configuration_form);
    $this->assertEquals('select', $configuration_form['extract_metadata']['#type']);
  }

  /**
   * Tests getMetadataAttributes() when metadata extraction is disabled.
   */
  public function testGetMetadataAttributesDisabled(): void {
    // Create media type with metadata extraction disabled.
    $media_type = $this->createMediaType('image', [
      'id' => 'image_no_metadata',
      'source_configuration' => [
        'source_field' => 'field_media_image',
        'extract_metadata' => 0,
      ],
    ]);

    $source = $media_type->getSource();
    $attributes = $source->getMetadataAttributes();

    // Should contain some default attributes (the exact keys may vary).
    $this->assertNotEmpty($attributes);

    // Should not contain our custom metadata attributes.
    $this->assertArrayNotHasKey('title', $attributes);
    $this->assertArrayNotHasKey('caption', $attributes);
  }

  /**
   * Tests getMetadataAttributes() when metadata extraction is enabled.
   */
  public function testGetMetadataAttributesEnabled(): void {
    $source = $this->mediaType->getSource();
    $attributes = $source->getMetadataAttributes();

    // Should contain some default attributes.
    $this->assertNotEmpty($attributes);

    // Should contain our custom metadata attributes.
    $expected_attributes = [
      'title', 'alt', 'caption', 'credit', 'byline', 'copyright',
      'date_created', 'city', 'province', 'country', 'source', 'headline',
      'category', 'supp_cat', 'camera_model', 'iso', 'exposure',
      'aperture', 'focal_length',
    ];

    foreach ($expected_attributes as $attribute) {
      $this->assertArrayHasKey($attribute, $attributes);
      // Attributes are TranslatableMarkup objects, not plain strings.
      $this->assertInstanceOf('Drupal\Core\StringTranslation\TranslatableMarkup', $attributes[$attribute]);
    }
  }

  /**
   * Tests getMetadata() with a real image file containing metadata.
   */
  public function testGetMetadataWithRealImage(): void {
    // Copy the test image to a temporary location.
    $test_image_source = __DIR__ . '/../../fixtures/example_iptc.jpg';
    $test_image_destination = 'public://example_iptc.jpg';
    $this->fileSystem->copy($test_image_source, $test_image_destination, FileExists::Replace);

    // Create a file entity.
    $file = File::create([
      'uri' => $test_image_destination,
      'filename' => 'example_iptc.jpg',
      'status' => 1,
    ]);
    $file->save();

    // Create a media entity.
    $media = Media::create([
      'bundle' => $this->mediaType->id(),
      'field_media_image' => [
        'target_id' => $file->id(),
      ],
    ]);
    $media->save();

    $source = $this->mediaType->getSource();

    // Test extracting specific metadata values.
    $title = $source->getMetadata($media, 'title');
    $this->assertEquals('The Title (ref2024.1)', $title);

    $caption = $source->getMetadata($media, 'caption');
    $this->assertEquals('The description aka caption (ref2024.1)', $caption);

    // Test that the 'default_name' returns the title metadata.
    $default_name = $source->getMetadata($media, 'default_name');
    $this->assertEquals('The Title (ref2024.1)', $default_name);

    // Test invalid attribute returns null.
    $invalid = $source->getMetadata($media, 'nonexistent_attribute');
    $this->assertNull($invalid);
  }

  /**
   * Tests getMetadata() when metadata extraction is disabled.
   */
  public function testGetMetadataDisabled(): void {
    // Create media type with metadata extraction disabled.
    $media_type = $this->createMediaType('image', [
      'id' => 'image_no_metadata_2',
      'source_configuration' => [
        'source_field' => 'field_media_image',
        'extract_metadata' => 0,
      ],
    ]);

    // Copy the test image to a temporary location.
    $test_image_source = __DIR__ . '/../../fixtures/example_iptc.jpg';
    $test_image_destination = 'public://example_iptc_2.jpg';
    $this->fileSystem->copy($test_image_source, $test_image_destination, FileExists::Replace);

    // Create a file entity.
    $file = File::create([
      'uri' => $test_image_destination,
      'filename' => 'example_iptc_2.jpg',
      'status' => 1,
    ]);
    $file->save();

    // Create a media entity.
    $media = Media::create([
      'bundle' => $media_type->id(),
      'field_media_image' => [
        'target_id' => $file->id(),
      ],
    ]);
    $media->save();

    $source = $media_type->getSource();

    // Metadata attributes should return null when extraction is disabled.
    $title = $source->getMetadata($media, 'title');
    $this->assertNull($title);

    // But default attributes should still work.
    $default_name = $source->getMetadata($media, 'default_name');
    $this->assertEquals('example_iptc_2.jpg', $default_name);
  }

  /**
   * Tests getMetadata() with missing file.
   */
  public function testGetMetadataWithMissingFile(): void {
    // Create a media entity without a file.
    $media = Media::create([
      'bundle' => $this->mediaType->id(),
    ]);
    $media->save();

    $source = $this->mediaType->getSource();

    // Should return null for metadata attributes when no file is present.
    $title = $source->getMetadata($media, 'title');
    $this->assertNull($title);
  }

  /**
   * Tests getMetadata() with non-existent file.
   */
  public function testGetMetadataWithNonExistentFile(): void {
    // Create a file entity pointing to a non-existent file.
    $file = File::create([
      'uri' => 'public://nonexistent.jpg',
      'filename' => 'nonexistent.jpg',
      'status' => 1,
    ]);
    $file->save();

    // Create a media entity.
    $media = Media::create([
      'bundle' => $this->mediaType->id(),
      'field_media_image' => [
        'target_id' => $file->id(),
      ],
    ]);
    $media->save();

    $source = $this->mediaType->getSource();

    // Should return null when file doesn't exist.
    $title = $source->getMetadata($media, 'title');
    $this->assertNull($title);
  }

  /**
   * Tests that default_name uses title metadata when available.
   */
  public function testDefaultNameUsesTitle(): void {
    // Copy the test image to a temporary location.
    $test_image_source = __DIR__ . '/../../fixtures/example_iptc.jpg';
    $test_image_destination = 'public://default_name_test.jpg';
    $this->fileSystem->copy($test_image_source, $test_image_destination, FileExists::Replace);

    // Create a file entity.
    $file = File::create([
      'uri' => $test_image_destination,
      'filename' => 'default_name_test.jpg',
      'status' => 1,
    ]);
    $file->save();

    // Create a media entity.
    $media = Media::create([
      'bundle' => $this->mediaType->id(),
      'field_media_image' => [
        'target_id' => $file->id(),
      ],
    ]);
    $media->save();

    $source = $this->mediaType->getSource();

    // Get the title and default_name metadata.
    $title = $source->getMetadata($media, 'title');
    $default_name = $source->getMetadata($media, 'default_name');

    // Both should return the same value (title from metadata).
    $this->assertIsString($title);
    $this->assertNotEmpty($title);
    $this->assertEquals($title, $default_name);
    // Should not be the filename.
    $this->assertNotEquals('default_name_test.jpg', $default_name);
  }

  /**
   * Tests that default_name falls back to filename when no title metadata.
   */
  public function testDefaultNameFallsBackToFilename(): void {
    // Create media type with metadata extraction disabled.
    $media_type = $this->createMediaType('image', [
      'id' => 'image_no_title',
      'source_configuration' => [
        'source_field' => 'field_media_image',
        'extract_metadata' => 0,
      ],
    ]);

    // Copy the test image to a temporary location.
    $test_image_source = __DIR__ . '/../../fixtures/example_iptc.jpg';
    $test_image_destination = 'public://filename_fallback_test.jpg';
    $this->fileSystem->copy($test_image_source, $test_image_destination, FileExists::Replace);

    // Create a file entity.
    $file = File::create([
      'uri' => $test_image_destination,
      'filename' => 'filename_fallback_test.jpg',
      'status' => 1,
    ]);
    $file->save();

    // Create a media entity.
    $media = Media::create([
      'bundle' => $media_type->id(),
      'field_media_image' => [
        'target_id' => $file->id(),
      ],
    ]);
    $media->save();

    $source = $media_type->getSource();

    // When metadata extraction is disabled, default_name should fall back to
    // parent behavior.
    $default_name = $source->getMetadata($media, 'default_name');
    $this->assertEquals('filename_fallback_test.jpg', $default_name);
  }

}
