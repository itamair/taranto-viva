<?php

declare(strict_types=1);

namespace Drupal\Tests\media_image_metadata\FunctionalJavascript;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\media_image_metadata\Plugin\media\Source\ImageWithMetadata;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\TestFileCreationTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the media image metadata functionality in a browser environment.
 */
#[Group('media_image_metadata')]
class MediaImageMetadataTest extends WebDriverTestBase {

  use MediaTypeCreationTrait;
  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'field_ui',
    'file',
    'image',
    'media',
    'media_library',
    'views',
    'filter',
    'editor',
    'media_image_metadata',
  ];

  /**
   * A user with permission to create and edit content.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * The media type for images with metadata.
   *
   * @var \Drupal\media\Entity\MediaType
   */
  protected $mediaType;

  /**
   * The content type that references media.
   *
   * @var \Drupal\node\Entity\NodeType
   */
  protected $nodeType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setupTestConfiguration();

    // Create a user with necessary permissions (after content types are
    // created).
    $this->adminUser = $this->drupalCreateUser([
      'access content',
      'create article content',
      'edit own article content',
      'administer media',
      'access media overview',
      'view media',
      'administer media types',
      'administer media form display',
      'administer node fields',
      'administer content types',
    ]);
  }

  /**
   * Sets up the test configuration with media type, fields, and content type.
   */
  protected function setupTestConfiguration(): void {
    // 1. Create an image media type with metadata extraction enabled.
    $this->mediaType = $this->createMediaType('image', [
      'id' => 'image_with_metadata',
      'label' => 'Image with Metadata',
      'source_configuration' => [
        'source_field' => 'field_media_image',
        'extract_metadata' => 1,
        'populate_alt_property' => 1,
      ],
    ]);

    // Ensure the media type uses our custom source plugin.
    // The hook_media_source_info_alter() should make the image source use
    // our class.
    $this->mediaType->set('source', 'image');
    $this->mediaType->save();

    // Verify the source plugin is using our custom class.
    $source = $this->mediaType->getSource();
    $this->assertInstanceOf(ImageWithMetadata::class, $source, 'Media type is using the custom source plugin');

    // 2. Create text fields for metadata mapping.
    $this->createTextField('media', 'image_with_metadata', 'field_caption', 'Caption');
    $this->createTextField('media', 'image_with_metadata', 'field_copyright', 'Copyright');

    // 3. Configure metadata field mapping in the media type.
    $field_map = [
      'caption' => 'field_caption',
      'copyright' => 'field_copyright',
    ];
    $this->mediaType->setFieldMap($field_map);
    $this->mediaType->save();

    // Verify the field map is set correctly.
    $this->assertEquals($field_map, $this->mediaType->getFieldMap(), 'Field map is configured correctly');
    // Clear caches to ensure the configuration is loaded properly.
    \Drupal::service('plugin.manager.media.source')->clearCachedDefinitions();
    drupal_flush_all_caches();

    // 4. Create a content type with a media reference field.
    $this->nodeType = NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $this->nodeType->save();

    // Create media reference field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_media_image',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'media',
      ],
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'article',
      'label' => 'Media Image',
      'settings' => [
        'handler' => 'default:media',
        'handler_settings' => [
          'target_bundles' => [
            'image_with_metadata' => 'image_with_metadata',
          ],
        ],
      ],
    ]);
    $field->save();

    // Configure the form display to use Media Library widget.
    $form_display = \Drupal::service('entity_display.repository')
      ->getFormDisplay('node', 'article', 'default');
    $form_display->setComponent('field_media_image', [
      'type' => 'media_library_widget',
      'settings' => [
        'media_types' => ['image_with_metadata'],
      ],
    ]);
    $form_display->save();

    // Configure the media form display to show the metadata fields.
    $media_form_display = \Drupal::service('entity_display.repository')
      ->getFormDisplay('media', 'image_with_metadata', 'default');
    $media_form_display->setComponent('field_caption', [
      'type' => 'string_textfield',
      'weight' => 1,
    ]);
    $media_form_display->setComponent('field_copyright', [
      'type' => 'string_textfield',
      'weight' => 2,
    ]);
    $media_form_display->save();

    // Configure media library form display as well.
    $media_library_form_display = \Drupal::service('entity_display.repository')
      ->getFormDisplay('media', 'image_with_metadata', 'media_library');
    if (!$media_library_form_display->isNew()) {
      $media_library_form_display->setComponent('field_caption', [
        'type' => 'string_textfield',
        'weight' => 1,
      ]);
      $media_library_form_display->setComponent('field_copyright', [
        'type' => 'string_textfield',
        'weight' => 2,
      ]);
      $media_library_form_display->save();
    }
  }

  /**
   * Creates a text field on the specified entity type and bundle.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param string $field_name
   *   The field name.
   * @param string $label
   *   The field label.
   */
  protected function createTextField(string $entity_type, string $bundle, string $field_name, string $label): void {
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'type' => 'string',
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
      'label' => $label,
    ]);
    $field->save();
  }

  /**
   * Waits for a file field to exist before uploading.
   */
  public function addMediaFileToField($locator, $path) {
    $page = $this->getSession()->getPage();
    $this->waitForFieldExists($locator);
    $page->attachFileToField($locator, $path);
  }

  /**
   * Waits for a field to exist.
   */
  protected function waitForFieldExists($field, $timeout = 10000) {
    $assert_session = $this->assertSession();
    $assert_session->waitForField($field, $timeout);
    return $assert_session->fieldExists($field);
  }

  /**
   * Asserts that media has been added.
   */
  protected function assertMediaAdded($index = 0) {
    $selector = '.js-media-library-add-form-added-media';

    // Assert that focus is shifted to the new media items.
    $this->assertJsCondition('jQuery("' . $selector . '").is(":focus")');

    $assert_session = $this->assertSession();
    $assert_session->pageTextMatches('/The media items? ha(s|ve) been created but ha(s|ve) not yet been saved. Fill in any required fields and save to add (it|them) to the media library./');
    $assert_session->elementAttributeContains('css', $selector, 'aria-label', 'Added media items');

    $this->assertElementExistsAfterWait('css', '[data-drupal-selector="edit-media-' . $index . '-fields"]');
    $assert_session->elementNotExists('css', '.js-media-library-menu');
  }

  /**
   * Asserts an element exists after waiting.
   */
  protected function assertElementExistsAfterWait($selector_type, $selector, $timeout = 10000) {
    $page = $this->getSession()->getPage();
    $element = $page->waitFor($timeout / 1000, function ($page) use ($selector_type, $selector) {
      return $page->find($selector_type, $selector);
    });
    $this->assertNotNull($element, "Element with selector '$selector' not found after waiting.");
    return $element;
  }

  /**
   * Clicks the "Save" button and waits for AJAX completion.
   */
  protected function pressSaveButton($expect_errors = FALSE) {
    $buttons = $this->assertElementExistsAfterWait('css', '.ui-dialog-buttonpane');
    $buttons->pressButton('Save');

    // Wait for AJAX operations to complete first.
    $this->assertSession()->assertWaitOnAjaxRequest();

    // If no validation errors are expected, wait for the "Insert selected"
    // button to return.
    if (!$expect_errors) {
      $result = $buttons->waitFor(10, function ($buttons) {
        /** @var \Behat\Mink\Element\NodeElement $buttons */
        return $buttons->findButton('Insert selected');
      });
      if (!$result) {
        // Try to wait a bit more and check again.
        $this->getSession()->wait(2000);
        $result = $buttons->findButton('Insert selected');
      }
      $this->assertNotEmpty($result, 'Insert selected button should appear after saving');
    }
  }

  /**
   * Clicks "Insert selected" button.
   */
  protected function pressInsertSelected($expected_announcement = NULL, bool $should_close = TRUE) {
    $this->assertSession()
      ->elementExists('css', '.ui-dialog-buttonpane')
      ->pressButton('Insert selected');

    if ($should_close) {
      $this->waitForNoText('Add or select media');
    }
    if ($expected_announcement) {
      $this->waitForText($expected_announcement);
    }
  }

  /**
   * Waits for text to disappear.
   */
  protected function waitForNoText($text, $timeout = 10000) {
    $page = $this->getSession()->getPage();
    $result = $page->waitFor($timeout / 1000, function ($page) use ($text) {
      $actual = preg_replace('/\s+/u', ' ', $page->getText());
      $regex = '/' . preg_quote($text, '/') . '/ui';
      return (bool) !preg_match($regex, $actual);
    });
    $this->assertNotEmpty($result, "\"$text\" was found but shouldn't be there.");
  }

  /**
   * Waits for text to appear.
   */
  protected function waitForText($text, $timeout = 10000) {
    $result = $this->assertSession()->waitForText($text, $timeout);
    $this->assertNotEmpty($result, "\"$text\" not found");
  }

  /**
   * Tests that metadata is pre-populated when uploading an image with metadata.
   */
  public function testMetadataPrePopulation(): void {
    $this->drupalLogin($this->adminUser);

    // Go to the node add form.
    $this->drupalGet('/node/add/article');

    // Wait for the page to load.
    $this->assertSession()->waitForElement('css', '.js-media-library-widget');

    // Click the "Add media" button to open the media library.
    $this->getSession()->getPage()->find('css', '.js-media-library-open-button')->click();

    // Wait for the media library modal to open.
    $this->assertSession()->waitForElement('css', '.ui-dialog');

    // Switch to the "Upload" tab if it's not already active.
    $upload_tab = $this->getSession()->getPage()->find('css', 'a[href="#media-library-add-form-wrapper"]');
    if ($upload_tab) {
      $upload_tab->click();
      $this->assertSession()->waitForElement('css', '#media-library-add-form-wrapper');
    }

    // Upload the test image.
    $test_image_path = __DIR__ . '/../../fixtures/example_iptc.jpg';
    $this->assertTrue(file_exists($test_image_path), 'Test image exists');

    $file_input = $this->getSession()->getPage()->find('css', 'input[type="file"]');
    $this->assertNotNull($file_input, 'File input found');

    $file_input->attachFile($test_image_path);

    // Wait for the file to be uploaded and the media to be created.
    $this->assertSession()->waitForElement('css', '.media-library-add-form__added-media', 10000);

    // Wait for the media creation message.
    $this->assertSession()->waitForText('The media item has been created but has not yet been saved', 10000);

    // Check that the ALT field is pre-populated with the expected value.
    $alt_field = $this->waitForFieldExists('Alternative text');
    $this->assertNotNull($alt_field, 'Alternative text field found');
    $alt_value = $alt_field->getValue();
    $this->assertEquals('This is the Alt Text description to support accessibility in 2024.1', $alt_value, 'ALT field is pre-populated with correct value');

    // Check that the caption field is pre-populated with the expected value.
    $caption_field = $this->waitForFieldExists('Caption');
    $this->assertNotNull($caption_field, 'Caption field found');

    $caption_value = $caption_field->getValue();
    $this->assertEquals('The description aka caption (ref2024.1)', $caption_value, 'Caption field is pre-populated with correct value');

    // Check that the copyright field is pre-populated with the expected
    // value.
    $copyright_field = $this->waitForFieldExists('Copyright');
    $this->assertNotNull($copyright_field, 'Copyright field found');

    $copyright_value = $copyright_field->getValue();
    $this->assertEquals('Copyright (Notice) 2024.1 IPTC - www.iptc.org  (ref2024.1)', $copyright_value, 'Copyright field is pre-populated with correct value');
  }

}
