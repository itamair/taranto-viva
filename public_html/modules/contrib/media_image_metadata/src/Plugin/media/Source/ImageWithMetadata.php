<?php

namespace Drupal\media_image_metadata\Plugin\media\Source;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Site\Settings;
use Drupal\media\MediaInterface;
use Drupal\media\Plugin\media\Source\Image;
use Drupal\media_image_metadata\Service\ImageMetadataHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Image media source, with metadata extraction capabilities.
 *
 * @see \Drupal\media\Plugin\media\Source\Image
 */
class ImageWithMetadata extends Image {

  /**
   * The image metadata helper service.
   *
   * @var \Drupal\media_image_metadata\Service\ImageMetadataHelper
   */
  protected ImageMetadataHelper $metadataHelper;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->metadataHelper = $container->get(ImageMetadataHelper::class);
    $instance->moduleHandler = $container->get('module_handler');
    $instance->logger = $container->get('logger.factory')->get('media_image_metadata');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $default_config = parent::defaultConfiguration();
    $default_config['extract_metadata'] = 0;
    $default_config['populate_alt_property'] = 0;
    return $default_config;
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() {
    $attributes = parent::getMetadataAttributes();

    if (!empty($this->configuration['extract_metadata'])) {
      foreach ($this->supportedMetadataAttributes() as $key => $label) {
        $attributes[$key] = $label;
      }
    }

    return $attributes;
  }

  /**
   * Defines which attributes are supported, and their human-readable labels.
   *
   * @return array
   *   An associative array where keys are the attribute machine names, and
   *   values are their corresponding human-readable labels.
   */
  private function supportedMetadataAttributes(): array {
    return [
      'title' => $this->t('Title'),
      'alt' => $this->t('Alt text'),
      'caption' => $this->t('Caption'),
      'credit' => $this->t('Credit'),
      'byline' => $this->t('Byline'),
      'copyright' => $this->t('Copyright'),
      'date_created' => $this->t('Date Created'),
      'city' => $this->t('City'),
      'province' => $this->t('Province'),
      'country' => $this->t('Country'),
      'source' => $this->t('Source'),
      'headline' => $this->t('Headline'),
      'category' => $this->t('Category'),
      'supp_cat' => $this->t('Supplemental Category'),
      'urgency' => $this->t('Urgency'),
      'camera_model' => $this->t('Camera Model'),
      'iso' => $this->t('ISO Speed'),
      'exposure' => $this->t('Exposure Time'),
      'aperture' => $this->t('Aperture'),
      'focal_length' => $this->t('Focal Length'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['extract_metadata'] = [
      '#type' => 'select',
      '#title' => $this->t('Extract embedded metadata'),
      '#description' => $this->t('This allows reading metadata that may exist inside the image file. After saving the form, you may use the mappings below to select which metadata items should go into Drupal fields.'),
      '#default_value' => $this->configuration['extract_metadata'],
      '#options' => [
        0 => $this->t('No'),
        1 => $this->t('Yes'),
      ],
    ];

    $form['populate_alt_property'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Populate ALT property'),
      '#description' => $this->t('Use the "alt" metadata attribute from the image file to pre-populate form ALT fields. If checked, you do not need to map the ALT attribute below, since this will happen automatically.'),
      '#default_value' => $this->configuration['populate_alt_property'],
      '#states' => [
        'visible' => [
          ':select[name="source_configuration[extract_metadata]"]' => ['value' => '1'],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $name) {
    if (empty($this->configuration['extract_metadata'])) {
      return parent::getMetadata($media, $name);
    }

    // Some attributes are handled by us, others by the parent. One exception
    // is 'default_name', for which we also want to provide info from embedded
    // metadata. The image source would default to the file name, which we also
    // do as a last fallback option.
    if ($name === 'default_name') {
      $name = 'title';
    }
    $our_attributes = array_keys($this->supportedMetadataAttributes());
    if (!in_array($name, $our_attributes, TRUE)) {
      return parent::getMetadata($media, $name);
    }

    // Get the file and image data.
    /** @var \Drupal\file\FileInterface $file */
    $file = $media->get($this->configuration['source_field'])->entity;
    // If the source field is not required, it may be empty.
    if (!$file) {
      return NULL;
    }

    $uri = $file->getFileUri();
    $file_path = $this->fileSystem->realpath($uri);
    if (!$file_path || !file_exists($file_path)) {
      return NULL;
    }

    try {
      $raw_metadata = $this->metadataHelper->getMetadataRaw($file_path);
      $normalized_metadata = $this->metadataHelper->normalizeMetadata($raw_metadata);
      // Always make sure the 'title' has a value, even if not from the embedded
      // info itself.
      if (empty($normalized_metadata['title'])) {
        $normalized_metadata['title'] = $file->getFilename();
      }
      $this->moduleHandler->alter('media_image_metadata', $normalized_metadata, $raw_metadata);
      if (!empty($normalized_metadata[$name]) && is_string($normalized_metadata[$name])) {
        return html_entity_decode(Xss::filter($normalized_metadata[$name]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
      }
    }
    catch (\Exception $e) {
      if (Settings::get('media_image_metadata_log_extraction_failures', FALSE)) {
        $this->logger->error("Failed to extract metadata: {$name} for image: {$file_path}. Error: {$e->getMessage()}");
      }
    }
    return NULL;
  }

}
