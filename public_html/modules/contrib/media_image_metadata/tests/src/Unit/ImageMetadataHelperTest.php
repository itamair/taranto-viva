<?php

declare(strict_types=1);

namespace Drupal\Tests\media_image_metadata\Unit;

use Drupal\media_image_metadata\Service\ImageMetadataHelper;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the ImageMetadataHelper service.
 */
#[CoversClass(\Drupal\media_image_metadata\Service\ImageMetadataHelper::class)]
#[Group('media_image_metadata')]
class ImageMetadataHelperTest extends UnitTestCase {

  /**
   * The image metadata helper service under test.
   *
   * @var \Drupal\media_image_metadata\Service\ImageMetadataHelper
   */
  protected ImageMetadataHelper $metadataHelper;

  /**
   * Path to the test image fixture.
   *
   * @var string
   */
  protected string $testImagePath;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->metadataHelper = new ImageMetadataHelper();
    $this->testImagePath = __DIR__ . '/../../fixtures/example_iptc.jpg';
  }

  /**
   * Tests getMetadataRaw() with a valid image file.
   */
  public function testGetMetadataRawValidImage(): void {
    $metadata = $this->metadataHelper->getMetadataRaw($this->testImagePath);

    $this->assertIsArray($metadata);
    $this->assertArrayHasKey('exif', $metadata);
    $this->assertArrayHasKey('iptc', $metadata);
    $this->assertArrayHasKey('xmp', $metadata);

    // Test that we get actual metadata from the test image.
    $this->assertNotEmpty($metadata['exif'], 'EXIF data should be present in test image');
    $this->assertNotEmpty($metadata['iptc'], 'IPTC data should be present in test image');
  }

  /**
   * Tests getMetadataRaw() with a non-existent file.
   */
  public function testGetMetadataRawNonExistentFile(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('File not readable: /non/existent/file.jpg');

    $this->metadataHelper->getMetadataRaw('/non/existent/file.jpg');
  }

  /**
   * Tests normalizeMetadata() with complete metadata.
   */
  public function testNormalizeMetadataComplete(): void {
    $raw_metadata = [
      'iptc' => [
        '2#005' => 'Test Title',
        '2#120' => 'Test Caption',
        '2#110' => 'Test Credit',
        '2#080' => 'Test Byline',
        '2#116' => 'Test Copyright',
        '2#055' => '20231201',
        '2#090' => 'Test City',
        '2#095' => 'Test Province',
        '2#101' => 'Test Country',
        '2#115' => 'Test Source',
        '2#105' => 'Test Headline',
        '2#015' => 'Test Category',
        '2#020' => 'Test Supplemental',
      ],
      'xmp' => [
        'dc:title|rdf:Alt|rdf:li' => 'XMP Title',
        'Iptc4xmpCore:AltTextAccessibility|rdf:Alt|rdf:li' => 'ALT Text',
        'dc:description|rdf:Alt|rdf:li' => 'XMP Description',
        'dc:rights|rdf:Alt|rdf:li' => 'XMP Rights',
        '@photoshop:Credit' => 'XMP Credit',
        '@photoshop:Headline' => 'XMP Headline',
        '@photoshop:DateCreated' => '2023-12-01',
      ],
      'exif' => [
        'IFD0' => [
          'DateTime' => '2023:12:01 12:00:00',
          'Model' => 'Test Camera',
          'ImageDescription' => 'EXIF Description',
          'Artist' => 'EXIF Artist',
          'Copyright' => 'EXIF Copyright',
        ],
        'EXIF' => [
          'Model' => 'Test Camera EXIF',
          'ISOSpeedRatings' => '800',
          'ExposureTime' => '1/60',
          'FNumber' => '28/10',
          'FocalLength' => '50/1',
        ],
        'FILE' => [
          'FileName' => 'test_image.jpg',
          'FileDateTime' => '1701432000',
        ],
      ],
    ];

    $normalized = $this->metadataHelper->normalizeMetadata($raw_metadata);

    $this->assertEquals('Test Title', $normalized['title']);
    $this->assertEquals('ALT Text', $normalized['alt']);
    $this->assertEquals('Test Caption', $normalized['caption']);
    $this->assertEquals('XMP Credit', $normalized['credit']);
    $this->assertEquals('Test Byline', $normalized['byline']);
    $this->assertEquals('Test Copyright', $normalized['copyright']);
    $this->assertEquals('20231201', $normalized['date_created']);
    $this->assertEquals('Test City', $normalized['city']);
    $this->assertEquals('Test Province', $normalized['province']);
    $this->assertEquals('Test Country', $normalized['country']);
    $this->assertEquals('Test Source', $normalized['source']);
    $this->assertEquals('Test Headline', $normalized['headline']);
    $this->assertEquals('Test Category', $normalized['category']);
    $this->assertEquals('Test Supplemental', $normalized['supp_cat']);
    $this->assertEquals('Test Camera', $normalized['camera_model']);
    $this->assertEquals('800', $normalized['iso']);
    $this->assertEquals('1/60', $normalized['exposure']);
    $this->assertEquals('2.8', $normalized['aperture']);
    $this->assertEquals('50', $normalized['focal_length']);
  }

  /**
   * Tests normalizeMetadata() with XMP fallback values.
   */
  public function testNormalizeMetadataXmpFallback(): void {
    $raw_metadata = [
      'iptc' => [],
      'xmp' => [
        'dc:title|rdf:Alt|rdf:li' => 'XMP Title',
        'dc:description|rdf:Alt|rdf:li' => 'XMP Description',
        'dc:rights|rdf:Alt|rdf:li' => 'XMP Rights',
        '@photoshop:Credit' => 'XMP Credit',
        '@photoshop:Headline' => 'XMP Headline',
      ],
      'exif' => [],
    ];

    $normalized = $this->metadataHelper->normalizeMetadata($raw_metadata);

    $this->assertEquals('XMP Title', $normalized['title']);
    $this->assertEquals('XMP Description', $normalized['caption']);
    $this->assertEquals('XMP Rights', $normalized['copyright']);
    $this->assertEquals('XMP Credit', $normalized['credit']);
    $this->assertEquals('XMP Headline', $normalized['headline']);
  }

  /**
   * Tests normalizeMetadata() with empty metadata.
   */
  public function testNormalizeMetadataEmpty(): void {
    $raw_metadata = [
      'iptc' => [],
      'xmp' => [],
      'exif' => [],
    ];

    $normalized = $this->metadataHelper->normalizeMetadata($raw_metadata);

    $this->assertNull($normalized['title']);
    $this->assertNull($normalized['alt']);
    $this->assertNull($normalized['caption']);
    $this->assertNull($normalized['credit']);
    $this->assertNull($normalized['byline']);
    $this->assertNull($normalized['copyright']);
    $this->assertNull($normalized['date_created']);
    $this->assertNull($normalized['city']);
    $this->assertNull($normalized['province']);
    $this->assertNull($normalized['country']);
    $this->assertNull($normalized['source']);
    $this->assertNull($normalized['headline']);
    $this->assertNull($normalized['category']);
    $this->assertNull($normalized['supp_cat']);
    $this->assertNull($normalized['camera_model']);
    $this->assertNull($normalized['iso']);
    $this->assertNull($normalized['exposure']);
    $this->assertNull($normalized['aperture']);
    $this->assertNull($normalized['focal_length']);
  }

  /**
   * Tests fraction normalization with various values.
   */
  #[DataProvider('fractionNormalizationProvider')]
  public function testFractionNormalization(string $input, $expected): void {
    // Use reflection to access the private method.
    $reflection = new \ReflectionClass($this->metadataHelper);
    $method = $reflection->getMethod('normalizeFraction');
    $method->setAccessible(TRUE);

    $result = $method->invoke($this->metadataHelper, $input);
    $this->assertEquals($expected, $result);
  }

  /**
   * Data provider for fraction normalization tests.
   *
   * @return array
   *   Test cases with input fraction and expected output.
   */
  public static function fractionNormalizationProvider(): array {
    return [
      'simple fraction' => ['1/60', '1/60'],
      'equal numerator and denominator' => ['10/10', '1'],
      'greater than 1, whole number' => ['60/10', 6],
      'greater than 1, decimal' => ['50/3', 16.67],
      'zero numerator' => ['0/10', '0'],
      'complex fraction less than 1' => ['3/15', '1/5'],
    ];
  }

  /**
   * Tests XMP extraction with actual test image.
   */
  public function testExtractXmpFromTestImage(): void {
    // Use reflection to access the private method.
    $reflection = new \ReflectionClass($this->metadataHelper);
    $method = $reflection->getMethod('extractXmp');
    $method->setAccessible(TRUE);

    $xmp_data = $method->invoke($this->metadataHelper, $this->testImagePath);
    $this->assertIsArray($xmp_data);
    // The test image should contain XMP data.
    $this->assertNotEmpty($xmp_data, 'XMP data should be present in test image');
  }

  /**
   * Tests firstNonEmpty helper method.
   */
  public function testFirstNonEmpty(): void {
    // Use reflection to access the private method.
    $reflection = new \ReflectionClass($this->metadataHelper);
    $method = $reflection->getMethod('firstNonEmpty');
    $method->setAccessible(TRUE);

    // Test with first value being non-empty.
    $result = $method->invoke($this->metadataHelper, ['first', 'second', 'third']);
    $this->assertEquals('first', $result);

    // Test with first value being empty, second non-empty.
    $result = $method->invoke($this->metadataHelper, ['', 'second', 'third']);
    $this->assertEquals('second', $result);

    // Test with first values being null/empty, third non-empty.
    $result = $method->invoke($this->metadataHelper, [NULL, '', 'third']);
    $this->assertEquals('third', $result);

    // Test with all values being empty.
    $result = $method->invoke($this->metadataHelper, ['', NULL, '']);
    $this->assertNull($result);

    // Test with empty array.
    $result = $method->invoke($this->metadataHelper, []);
    $this->assertNull($result);
  }

  /**
   * Tests metadata prioritization with fallback values.
   */
  public function testMetadataPrioritization(): void {
    // Test title prioritization: IPTC 2#005 > XMP dc:title > IPTC 2#105
    // > EXIF ImageDescription > EXIF FileName.
    $raw_metadata = [
      'iptc' => [
        '2#105' => 'IPTC Headline',
      ],
      'xmp' => [
        'dc:title|rdf:Alt|rdf:li' => 'XMP Title',
      ],
      'exif' => [
        'IFD0' => [
          'ImageDescription' => 'EXIF Description',
        ],
        'FILE' => [
          'FileName' => 'test_image.jpg',
        ],
      ],
    ];

    $normalized = $this->metadataHelper->normalizeMetadata($raw_metadata);
    // Should use XMP title as IPTC 2#005 is not present.
    $this->assertEquals('XMP Title', $normalized['title']);

    // Test alt prioritization: XMP Iptc4xmpCore:AltTextAccessibility
    // > IPTC 2#105 > IPTC 2#120.
    $raw_metadata = [
      'iptc' => [
        '2#105' => 'IPTC Headline',
        '2#120' => 'IPTC Caption',
      ],
      'xmp' => [
        'Iptc4xmpCore:AltTextAccessibility|rdf:Alt|rdf:li' => 'XMP Alt Text',
      ],
      'exif' => [],
    ];

    $normalized = $this->metadataHelper->normalizeMetadata($raw_metadata);
    // Should use XMP alt text as it has priority.
    $this->assertEquals('XMP Alt Text', $normalized['alt']);

    // Test credit prioritization: XMP @photoshop:Credit > IPTC 2#110
    // > EXIF Artist.
    $raw_metadata = [
      'iptc' => [],
      'xmp' => [
        '@photoshop:Credit' => 'XMP Credit',
      ],
      'exif' => [
        'IFD0' => [
          'Artist' => 'EXIF Artist',
        ],
      ],
    ];

    $normalized = $this->metadataHelper->normalizeMetadata($raw_metadata);
    // Should use XMP Credit as it has priority over EXIF Artist.
    $this->assertEquals('XMP Credit', $normalized['credit']);
  }

}
