<?php

namespace Drupal\entity_mesh;

use Drupal\Core\Entity\EntityInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;

/**
 * Service to perform database operations.
 */
interface RepositoryInterface {

  /**
   * The entity mesh table.
   */
  const ENTITY_MESH_TABLE = 'entity_mesh';

  /**
   * Get the database service.
   */
  public function getDatabaseService();

  /**
   * Get logger.
   */
  public function getLogger();

  /**
   * Insert multiple rows in one execution.
   *
   * @param \Drupal\entity_mesh\SourceInterface $source
   *   Source target object.
   *
   * @return bool
   *   TRUE if the operation was successful, FALSE otherwise.
   */
  public function insertSource(SourceInterface $source): bool;

  /**
   * Remove all the rows of one source.
   *
   * @param \Drupal\entity_mesh\SourceInterface $source
   *   Source object.
   *
   * @return bool
   *   TRUE if the operation was successful, FALSE otherwise.
   */
  public function deleteSource(SourceInterface $source): bool;

  /**
   * Remove all the rows of one source by type.
   *
   * @param string $type
   *   The type of the source.
   * @param array $conditions
   *   Additional conditions to delete the source.
   *
   * @return bool
   *   TRUE if the operation was successful, FALSE otherwise.
   */
  public function deleteSourceByType(string $type, array $conditions): bool;

  /**
   * Remove all the rows.
   *
   * @return bool
   *   TRUE if the operation was successful, FALSE otherwise.
   */
  public function deleteAllSourceTarget(): bool;

  /**
   * Save the source and its targets in database.
   *
   * @param \Drupal\entity_mesh\SourceInterface $source
   *   Source with targets to save.
   *
   * @return bool
   *   TRUE if the operation was successful, FALSE otherwise.
   */
  public function saveSource(SourceInterface $source): bool;

  /**
   * Instance an empty source object.
   *
   * @return \Drupal\entity_mesh\SourceInterface
   *   The source object.
   */
  public function instanceEmptySource(): SourceInterface;

  /**
   * Instance an empty target object.
   *
   * @return \Drupal\entity_mesh\TargetInterface
   *   The target object.
   */
  public function instanceEmptyTarget(): TargetInterface;

  /**
   * Get the path from a file URL.
   *
   * @param string $path
   *   Internal url.
   *
   * @return string|null
   *   The path or null if it is not a file.
   */
  public function getPathFromFileUrl(string $path): ?string;

  /**
   * Parse an image style URL to extract style name, scheme, and file path.
   *
   * Detects URLs with the pattern:
   * [base_path]/styles/{STYLE_NAME}/{SCHEME}/{FILE_PATH}
   *
   * This method handles different public file path configurations:
   * - sites/default/files/styles/thumbnail/public/image.jpg
   * - sites/example.com/files/styles/thumbnail/public/image.jpg
   * - files/styles/thumbnail/public/image.jpg
   *
   * @param string $path
   *   The URL path to parse.
   *
   * @return array|null
   *   Array with keys 'style', 'scheme', and 'path' if the URL is an image
   *   style URL, or NULL if it's not an image style URL.
   *   Example: [
   *    'style' => 'thumbnail',
   *    'scheme' => 'public',
   *    'path' => 'image.jpg',
   *   ]
   */
  public function parseImageStyleUrl(string $path): ?array;

  /**
   * Extract the original file URI from an image style URL.
   *
   * Converts an image style URL like [base_path]/styles/thumbnail/public/
   * image.jpg to the original file URI public://image.jpg.
   *
   * Works with any configured public file path (sites/default/files,
   * sites/example.com/files, files, etc.).
   *
   * @param string $path
   *   The image style URL path.
   *
   * @return string|null
   *   The original file URI (e.g., 'public://image.jpg'), or NULL if the path
   *   is not a valid image style URL.
   */
  public function getOriginalFileUriFromStyleUrl(string $path): ?string;

  /**
   * Get the file object from the url.
   *
   * @param string $path
   *   The path of the file.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file object or NULL if the file does not exist.
   */
  public function getFileFromUrl($path): ?FileInterface;

  /**
   * Get the media object by the file entity.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity.
   *
   * @return \Drupal\media\MediaInterface|null
   *   The media object or NULL if the media does not exist.
   */
  public function getMediaFileByEntityFile(FileInterface $file): ?MediaInterface;

  /**
   * Check if exists a redirection for this path.
   *
   * @param string $path
   *   Path to check.
   * @param string $langcode
   *   Language code.
   * @param int $count
   *   Number of redirection to check.
   *
   * @return mixed
   *   The redirection or NULL if it does not exist.
   */
  public function ifRedirectionForPath(string $path, string $langcode, int $count = 0);

  /**
   * Get the path without the lang prefix.
   *
   * @param string $path
   *   The path.
   *
   * @return string
   *   The path without the lang prefix.
   */
  public function getPathWithoutLangPrefix($path);

  /**
   * Get the langcode from the path.
   *
   * @param string $path
   *   The path.
   *
   * @return string|null
   *   The langcode or NULL.
   */
  public function getLangcodeFromPath($path): ?string;

  /**
   * Get the lang prefix from the path.
   *
   * @param string $path
   *   The path.
   *
   * @return string|null
   *   The langcode or NULL.
   */
  public function getLangPrefixFromPath($path): ?string;

  /**
   * Get a DummyAccount configured with analyzer roles.
   *
   * @return \Drupal\entity_mesh\DummyAccount
   *   A DummyAccount instance configured with the roles specified in the
   *   module settings. If no roles are configured returns an anonymous account.
   */
  public function getMeshAccount(): DummyAccount;

  /**
   * Clears the cached mesh account.
   *
   * This method should be called when the analyzer_account configuration
   * changes to ensure the new configuration is used.
   */
  public function clearMeshAccountCache();

  /**
   * Check if the configured account has view access to a specific entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   TRUE if the account can view the entity, FALSE otherwise.
   */
  public function checkViewAccessEntity(EntityInterface $entity): bool;

}
