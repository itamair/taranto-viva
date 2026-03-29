<?php

namespace Drupal\image_style_warmer;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DestructableInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Queue\QueueFactory;
use Drupal\file\FileInterface;
use Drupal\file\Validation\FileValidatorInterface;

/**
 * Defines an images styles warmer.
 */
class ImageStylesWarmer implements ImageStylesWarmerInterface, DestructableInterface {

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The file entity storage.
   *
   * @var \Drupal\file\FileStorageInterface
   */
  protected $file;

  /**
   * The file entity to warmup.
   *
   * @var array
   *   Array of \Drupal\file\FileInterface objects to warmup.
   */
  protected $warmupFiles = [];

  /**
   * The image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * The image style entity storage.
   *
   * @var \Drupal\image\ImageStyleStorageInterface
   */
  protected $imageStyles;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The file validator.
   *
   * @var \Drupal\file\Validation\FileValidatorInterface
   * */
  protected $fileValidator;

  /**
   * Constructs a ImageStylesWarmer object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   *   The image factory.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\file\Validation\FileValidatorInterface $file_validator
   *   The file validator.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, ImageFactory $image_factory, QueueFactory $queue_factory, FileValidatorInterface $file_validator) {
    $this->config = $config_factory->get('image_style_warmer.settings');
    $this->file = $entity_type_manager->getStorage('file');
    $this->imageFactory = $image_factory;
    $this->imageStyles = $entity_type_manager->getStorage('image_style');
    $this->queueFactory = $queue_factory;
    $this->fileValidator = $file_validator;
  }

  /**
   * {@inheritdoc}
   */
  public function warmUp(FileInterface $file) {

    // Add file for later initial warmup via destructor.
    $this->warmupFiles[] = $file;

    // Add file to queue for later warmup via cron.
    $queueImageStyles = $this->config->get('queue_image_styles');
    if (!empty($queueImageStyles)) {
      $this->addQueue($file, array_keys($queueImageStyles));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function doWarmUp(FileInterface $file, array $image_styles) {
    if (empty($image_styles) || !$this->validateImage($file)) {
      return;
    }

    /** @var \Drupal\Core\Image\Image $image */
    /** @var \Drupal\image\Entity\ImageStyle $style */

    // Create image derivatives if they not already exists.
    $styles = $this->imageStyles->loadMultiple($image_styles);
    $image_uri = $file->getFileUri();
    foreach ($styles as $style) {
      $derivative_uri = $style->buildUri($image_uri);
      if (!file_exists($derivative_uri)) {
        $style->createDerivative($image_uri, $derivative_uri);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addQueue(FileInterface $file, array $image_styles) {
    if (!empty($image_styles) && $this->validateImage($file)) {
      $queue = $this->queueFactory->get('image_style_warmer_pregenerator');
      $data = ['file_id' => $file->id(), 'image_styles' => $image_styles];
      $queue->createItem($data);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateImage(FileInterface $file) {
    if (!$file->isPermanent()) {
      return FALSE;
    }
    $extensions = implode(' ', $this->imageFactory->getSupportedExtensions());

    // @todo Remove when Drupal 10.2.0 or higher is the minimum version.
    if (version_compare(\Drupal::VERSION, '10.2.0', '<')) {
      $validators = [
        'file_validate_extensions' => $extensions,
      ];
    }
    else {
      $validators = [
        'FileExtension' => [
          'extensions' => $extensions,
        ],
      ];
    }

    /** @var \Symfony\Component\Validator\ConstraintViolationListInterface $violations */
    $violations = $this->fileValidator->validate($file, $validators);
    return $violations->count() === 0;
  }

  /**
   * {@inheritdoc}
   */
  public function destruct() {
    if (!empty($this->warmupFiles)) {
      $initialImageStyles = $this->config->get('initial_image_styles');
      if (!empty($initialImageStyles)) {
        foreach ($this->warmupFiles as $warmup_file) {
          $this->doWarmUp($warmup_file, array_keys($initialImageStyles));
        }
      }
    }
  }

}
