<?php

namespace Drupal\Tests\photoswipe_dynamic_caption\FunctionalJavascript;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\photoswipe\FunctionalJavascript\PhotoswipeJsTestBase;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests the photoswipe_dynamic_caption module.
 *
 * @group photoswipe
 */
class CaptionTest extends PhotoswipeJsTestBase {
  use TestFileCreationTrait, EntityReferenceFieldCreationTrait, MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'test_page_test',
    'file',
    'image',
    'media',
    'node',
    'field_ui',
    'photoswipe',
    'photoswipe_dynamic_caption',
  ];

  /**
   * Tests if caption is visible.
   */
  public function testPhotoswipeCaptionAltText() {
    $session = $this->assertSession();

    // Create the image field on the node 'article':
    $this->createImageField(
          'field_image',
          'node',
          'article',
          ['cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED],
          [],
          [],
          [
            'photoswipe_thumbnail_style_first' => '',
            'photoswipe_thumbnail_style' => '',
            'photoswipe_image_style' => '',
            'photoswipe_reference_image_field' => '',
            'photoswipe_view_mode' => '',
          ],
          'photoswipe_field_formatter',
          '',
          [
            'photoswipe_dynamic_caption' => [
              'photoswipe_caption' => 'alt',
            ],
          ]
        );

    $imageAlt = 'Test alt';

    // Setup an image node:
    $fileFieldEntries = [];
    $file = File::create([
      'uri' => $this->getTestFiles('image')[0]->uri,
    ]);
    $file->save();
    $fileFieldEntries[] = [
      'target_id' => $file->id(),
      'alt' => $imageAlt,
      'title' => 'bla',
    ];

    $node = $this->createNode([
      'title' => 'Test',
      'type' => 'article',
      'field_image' => $fileFieldEntries,
    ]);
    $node->save();

    $this->drupalGet('/node/1');

    $this->assertNotNull($session->waitForElement('css', '.photoswipe-gallery'));
    // Check if the image has an 'alt' attribute:
    $session->elementAttributeContains('css', 'a.photoswipe > img', 'alt', $imageAlt);
    // Open the photoswipe layer.
    $this->getSession()->getPage()->find('css', 'a[href*="image-test.png"].photoswipe')->click();
    $this->assertNotNull($session->waitForElementVisible('css', '.pswp'));
    $session->elementTextEquals('css', '.pswp__dynamic-caption', $imageAlt);
  }

  /**
   * Tests the photoswipe caption from referenced media entity field.
   */
  public function testPhotoswipeCaptionReferencedEntity() {
    $session = $this->assertSession();

    // Create an image media type.
    $this->createMediaType('image', ['id' => 'image']);

    // Create a text field on the media type for caption source.
    $this->createTextField('field_caption_source', 'media', 'image');

    // Create a media entity with caption content.
    $mediaCaptionText = 'Media entity caption text';
    $file = File::create([
      'uri' => $this->getTestFiles('image')[0]->uri,
    ]);
    $file->save();

    $media = Media::create([
      'bundle' => 'image',
      'name' => 'Test Media',
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => 'Media alt text',
      ],
      'field_caption_source' => $mediaCaptionText,
    ]);
    $media->save();

    // Create an entity reference field on node pointing to media entities.
    $this->createEntityReferenceField(
      'node',
      'article',
      'field_media_reference',
      'Media Reference',
      'media',
      'default',
      ['target_bundles' => ['image']],
      FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
    );

    // Configure the entity reference field display to use photoswipe formatter
    // with caption from referenced media entity field.
    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');
    $display_repository->getViewDisplay('node', 'article')
      ->setComponent('field_media_reference', [
        'type' => 'photoswipe_field_formatter',
        'settings' => [
          'photoswipe_thumbnail_style_first' => '',
          'photoswipe_thumbnail_style' => '',
          'photoswipe_image_style' => '',
          'photoswipe_reference_image_field' => 'field_media_image',
          'photoswipe_view_mode' => '',
        ],
        'third_party_settings' => [
          'photoswipe_dynamic_caption' => [
            'photoswipe_caption' => 'photoswipe_dynamic_caption_referenced_entity_field_caption_source',
          ],
        ],
      ])
      ->save();

    // Create a node with reference to the media entity.
    $node = $this->createNode([
      'title' => 'Test Node with Media Reference',
      'type' => 'article',
      'field_media_reference' => [['target_id' => $media->id()]],
    ]);
    $node->save();

    $this->drupalGet('/node/' . $node->id());

    $this->assertNotNull($session->waitForElement('css', '.photoswipe-gallery'));
    // Open the photoswipe layer.
    $this->getSession()->getPage()->find('css', 'a[href*="image-test.png"].photoswipe')->click();
    $this->assertNotNull($session->waitForElementVisible('css', '.pswp'));

    // Verify the caption shows the text from the referenced media entity's
    // field:
    $session->elementTextEquals('css', '.pswp__dynamic-caption', $mediaCaptionText);
  }

  /**
   * Helper method to create a text field.
   */
  protected function createTextField($field_name, $entity_type, $bundle) {
    $entityTypeManager = $this->container->get('entity_type.manager');

    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'type' => 'string',
    ]);
    $field_storage->save();

    $field_config = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
      'label' => 'Caption Source Field',
    ]);
    $field_config->save();

    // Set the form display.
    $form_display = $entityTypeManager
      ->getStorage('entity_form_display')
      ->load($entity_type . '.' . $bundle . '.default');
    if (!$form_display) {
      $form_display = EntityFormDisplay::create([
        'targetEntityType' => $entity_type,
        'bundle' => $bundle,
        'mode' => 'default',
      ]);
    }
    $form_display->setComponent($field_name, [
      'type' => 'string_textfield',
    ])->save();

    // Set the view display.
    $view_display = $entityTypeManager
      ->getStorage('entity_view_display')
      ->load($entity_type . '.' . $bundle . '.default');
    if (!$view_display) {
      $view_display = EntityViewDisplay::create([
        'targetEntityType' => $entity_type,
        'bundle' => $bundle,
        'mode' => 'default',
      ]);
    }
    $view_display->setComponent($field_name, [
      'type' => 'string',
    ])->save();
  }

  // @todo Add tests for the rest of the possible "photoswipe_caption" values
  // here.
}
