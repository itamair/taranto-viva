<?php

namespace Drupal\image_styles_generator\Commands;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\image_styles_generator\DerivativeWarmerInterface;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class ImageStylesGeneratorCommands extends DrushCommands {

  /**
   * Used to create / delete entities.
   *
   * @var \Drupal\image_styles_generator\DerivativeWarmerInterface
   */
  protected DerivativeWarmerInterface $derivativeWarmer;

  /**
   * Used to create / delete entities.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Construct the command.
   */
  public function __construct(DerivativeWarmerInterface $derivative_warmer, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct();
    $this->entityTypeManager = $entity_type_manager;
    $this->derivativeWarmer = $derivative_warmer;
  }

  /**
   * Generate all derivatives for all image styles.
   *
   * @todo only generate the necessary image styles. For this,
   * join with the file usage table and load the content with
   * images, then for each field that has an image, get its image
   * styles from its display formatters information and generate
   * images only for those image styles.
   *
   * @command image:derive:multiple
   *
   * @option image-styles
   *   Coma separated image styles IDs.
   * @option image_styles
   *   DEPRECATED, please use --image-styles instead.
   *
   * @option skip-existing
   *   Option to skip generating existing derivative images.
   * @option skip_existing
   *   DEPRECATED, please use --skip-existing instead.
   *
   * @usage image:derive:multiple
   *   Generate all derivatives for all image styles.
   * @usage image:derive:multiple --skip-existing
   *   Generate all derivatives for non-existing image files.
   * @usage image:derive:multiple --image-styles=large,thumbnail
   *   Generate "thumbnail" and "large" derivatives for all image styles.
   *
   * @aliases idm, image_derivatives:generate
   */
  public function generateAllDerivativesForAllImagesInDatabase(
    $options = [
      'image_styles' => NULL,
      'image-styles' => NULL,
      'skip-existing' => FALSE,
      'skip_existing' => FALSE,
    ],
  ) {

    $default_image_styles_value = NULL;
    $default_skip_existing_value = FALSE;

    if ($options['image_styles'] !== NULL) {
      $this->logger->warning('--image_styles option is deprecated. Please use --image-styles instead.');
      // Use this option value as default so it will be overridden by the
      // --image-styles option if provided.
      $default_image_styles_value = $options['image_styles'];
    }

    if ($options['skip_existing']) {
      $this->logger->warning('--skip_existing option is deprecated. Please use --skip-existing instead.');
      // Use this option value as default so it will be overridden by the
      // --skip-existing option if provided.
      $default_skip_existing_value = TRUE;
      print "$default_skip_existing_value\n";
    }

    $image_styles_ids = $options['image-styles'] ? explode(',', $options['image-styles']) : $default_image_styles_value;
    $skip_existing = $options['skip-existing'] || $default_skip_existing_value;

    try {
      $image_styles = $this->loadImageStyles($image_styles_ids);
      $fids = $this->loadAllImageFiles();
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      $this->logger->error($e->getMessage());
      return;
    }

    $total = count($image_styles) * count($fids);
    $this->output()->writeln('Found ' . count($image_styles) . ' image styles and ' . count($fids) . ' images. ' . $total . ' derivatives to generate.');
    $progress_bar = $this->initializeProgressBar(count($image_styles) * count($fids));
    $skipped = 0;
    foreach ($fids as $fid) {
      $file = $this->entityTypeManager->getStorage('file')->load($fid);
      foreach ($image_styles as $image_style) {
        $derivative_uri = $image_style->buildUri($file->getFileUri());
        // Skip if derivative image file already exist.
        if ($skip_existing && file_exists($derivative_uri)) {
          $skipped++;
          continue;
        }
        // Create derivative image file.
        $this->derivativeWarmer->regenerateImageStyleDerivativeFromFile($image_style, $file);
        $progress_bar->advance();
      }
    }
    $progress_bar->finish();



    if ($skipped > 0) {
      $this->output()->writeln("\n$skipped were skipped because they already exist.");
    }
    $this->output()->writeln("All derivative images have been generated.");


  }

  /**
   * Load all image files from database.
   *
   * @return array
   *   Returns array of file ids.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function loadAllImageFiles(): array {
    $query = $this->entityTypeManager->getStorage('file')->getQuery()->accessCheck();
    $query->condition('filemime', 'image%', 'LIKE');
    $query->condition('status', 1);
    $query->sort('fid', 'DESC');
    return $query->execute();
  }

  /**
   * Load image passed or all image style objects.
   *
   * @param array|null $image_styles_ids
   *   Array of image styles ids.
   *
   * @return array
   *   Returns an array of image style objects found.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function loadImageStyles(array|null $image_styles_ids = NULL): array {
    return $this->entityTypeManager->getStorage('image_style')
      ->loadMultiple($image_styles_ids);
  }

  /**
   * Initializes progressBar object.
   *
   * @param string $total
   *   Total number of items to process.
   *
   * @return \Symfony\Component\Console\Helper\ProgressBar
   *   Returns a progress bar object.
   */
  public function initializeProgressBar(string $total): ProgressBar {
    $progress_bar = new ProgressBar($this->output, $total);
    $progress_bar->setFormat('very_verbose');
    return $progress_bar;
  }

}
