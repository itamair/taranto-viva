<?php

namespace Drupal\entity_mesh;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\redirect\Entity\Redirect;
use Drupal\redirect\Exception\RedirectLoopException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service to perform database operations.
 */
class Repository implements RepositoryInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Url language prefixes.
   *
   * @var array
   */
  protected array $prefixes;

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface|null
   */
  protected $fileUrlGenerator;

  /**
   * Cached mesh account.
   *
   * @var \Drupal\entity_mesh\DummyAccount|null
   */
  protected $meshAccount;

  /**
   * Cache for file URI lookups to avoid repeated parsing.
   *
   * Static cache that persists for the duration of a single request,
   * preventing redundant parsing when the same image style
   * derivative is encountered multiple times.
   *
   * @var array
   */
  protected array $fileUriCache = [];

  /**
   * Known image file extensions for derivative detection.
   *
   * @var array
   */
  protected const IMAGE_EXTENSIONS = [
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif',
    'bmp', 'svg', 'tiff', 'tif', 'ico', 'heic',
    'heif', 'jxl',
  ];

  /**
   * Constructs a new Repository object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface|null $file_url_generator
   *   The file URL generator (optional for backwards compatibility, for
   *   example, when used in legacy code paths).
   */
  public function __construct(
    Connection $database,
    LoggerInterface $logger,
    RequestStack $request_stack,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    ConfigFactoryInterface $config_factory,
    FileUrlGeneratorInterface $file_url_generator,
  ) {
    $this->database = $database;
    $this->logger = $logger;
    $this->requestStack = $request_stack;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->configFactory = $config_factory;
    $this->fileUrlGenerator = $file_url_generator;
    $this->prefixes = $config_factory->get('language.negotiation')->get('url.prefixes');
  }

  /**
   * {@inheritdoc}
   */
  public function getDatabaseService() {
    return $this->database;
  }

  /**
   * {@inheritdoc}
   */
  public function getLogger() {
    return $this->logger;
  }

  /**
   * {@inheritdoc}
   */
  public function insertSource(SourceInterface $source): bool {
    $rows = [];

    // We ensure that the Source object has the Hash ID.
    if ($source->getHashId() === NULL) {
      $source->setHashId();
    }

    // We ensure that the Source object has the title.
    $this->setTitleSourceTarget($source);

    $targets = $source->getTargets();
    if (empty($targets)) {
      return TRUE;
    }
    $source_properties = $source->toArray();

    foreach ($targets as $target) {
      $row = [];
      // We ensure that the Target object has the Hash ID.
      if ($target->getHashId() === NULL) {
        $target->setHashId();
      }
      // We ensure that the Target object has the title.
      $this->setTitleSourceTarget($target);

      $row = array_merge($source_properties, $target->toArray());

      $rows[] = $row;
    }

    $transaction = $this->database->startTransaction();

    try {
      $query = $this->database->insert(self::ENTITY_MESH_TABLE)
        ->fields(array_keys(reset($rows)));

      foreach ($rows as $row) {
        $query->values($row);
      }

      $query->execute();
    }
    catch (\Exception $e) {
      $transaction->rollback();
      $this->logger->error($e->getMessage());
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Build label for objects.
   *
   * @param object $object
   *   Source or Target object.
   */
  protected function setTitleSourceTarget($object) {
    $title_segment_1 = '';

    if ($object instanceof SourceInterface) {
      if (!empty($object->getTitle())) {
        return;
      }
      $entity_type = $object->getSourceEntityType();
      $id = $object->getSourceEntityId();
      $langcode = $object->getSourceEntityLangcode();
      $title_segment_1 = $this->getLabel($entity_type, $id, $langcode) ?? '';
    }
    elseif ($object instanceof TargetInterface) {
      if (!empty($object->getTitle())) {
        return;
      }
      if ($object->getLinkType() === 'external') {
        $entity_type = 'external';
        $id = NULL;
      }
      else {
        $entity_type = $object->getEntityType();
        $id = $object->getEntityId();
        $langcode = $object->getEntityLangcode();
        $title_segment_1 = $this->getLabel($entity_type, $id, $langcode);
      }
      if (empty($title_segment_1)) {
        $title_segment_1 = $object->getHref() ?? '';
      }
    }
    else {
      return;
    }

    $label = $title_segment_1;
    $label .= ' (' . $entity_type;
    $label .= empty($id) ? ')' : ' - ' . $id . ')';
    if ($label === ' ()') {
      return;
    }
    $label = mb_substr($label, 0, 255);

    $object->setTitle($label);
  }

  /**
   * Get label from an entity.
   *
   * @param string|null $entity_type
   *   The entity type.
   * @param string|null $entity_id
   *   The entity id.
   * @param string|null $langcode
   *   The langcode.
   *
   * @return string|null
   *   The string of the link or entity id.
   */
  protected function getLabel(?string $entity_type, ?string $entity_id, ?string $langcode): ?string {

    if (empty($entity_id) || empty($entity_type)) {
      return NULL;
    }

    try {
      $storage = $this->entityTypeManager->getStorage($entity_type);
    }
    catch (PluginNotFoundException $e) {
      return NULL;
    }

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $storage->load($entity_id);
    if (!$entity instanceof EntityInterface) {
      return NULL;
    }

    return !empty($langcode) && $entity instanceof TranslatableInterface && $entity->isTranslatable() && $entity->hasTranslation($langcode) ? $entity->getTranslation($langcode)->label() : $entity->label();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteSource(SourceInterface $source): bool {
    try {
      $this->database
        ->delete(self::ENTITY_MESH_TABLE)
        ->condition('type', $source->getType())
        ->condition('source_entity_type', $source->getSourceEntityType())
        ->condition('source_entity_id', $source->getSourceEntityId())
        ->execute();
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteSourceByType(string $type, array $conditions): bool {
    try {
      $query = $this->database->delete(self::ENTITY_MESH_TABLE);
      $query->condition('type', $type);
      foreach ($conditions as $field => $value) {
        $query->condition($field, $value);
      }
      $query->execute();
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAllSourceTarget(): bool {
    try {
      $this->database
        ->delete(self::ENTITY_MESH_TABLE)
        ->execute();
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function saveSource(SourceInterface $source): bool {
    if (!$this->insertSource($source)) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function instanceEmptySource(): SourceInterface {
    return Source::create();
  }

  /**
   * {@inheritdoc}
   */
  public function instanceEmptyTarget(): TargetInterface {
    $self_domain_internal = $this->configFactory->get('entity_mesh.settings')->get('self_domain_internal');
    return Target::create($this->requestStack, $self_domain_internal);
  }

  /**
   * Get the public files base path dynamically.
   *
   * @return string|null
   *   The public files base path (e.g., 'sites/default/files') or NULL.
   */
  protected function getPublicFilesBasePath(): ?string {
    if (!$this->fileUrlGenerator) {
      return NULL;
    }

    $publicUrl = $this->fileUrlGenerator->generateString('public://');

    // Extract just the path portion (not the full URL with domain).
    $path = parse_url($publicUrl, PHP_URL_PATH);

    if (!$path) {
      return NULL;
    }

    // Return the path without leading or trailing slashes.
    return trim($path, '/');
  }

  /**
   * {@inheritdoc}
   */
  public function getPathFromFileUrl(string $path): ?string {
    // Get the actual public files base path dynamically.
    $public_base_path = $this->getPublicFilesBasePath();
    if (!$public_base_path) {
      return NULL;
    }

    // Normalize the path by removing leading/trailing slashes.
    $path = trim($path, '/');

    // Check if the path starts with the public base path.
    if (strpos($path, $public_base_path) !== 0) {
      return NULL;
    }

    // Check if it's an image style URL (contains /styles/).
    if (strpos($path, '/styles/') !== FALSE) {
      $original_uri = $this->getOriginalFileUriFromStyleUrl($path);
      if ($original_uri) {
        return $original_uri;
      }
      // If we couldn't parse it as an image style URL, fall through to
      // the direct file logic below.
    }

    // Direct file URL - convert to URI format.
    // Remove the base path and prepend 'public://'.
    // Example: 'sites/default/files/image.jpg' -> 'public://image.jpg'.
    $file_path = substr($path, strlen($public_base_path));
    $file_path = ltrim($file_path, '/');

    return 'public://' . $file_path;
  }

  /**
   * {@inheritdoc}
   */
  public function parseImageStyleUrl(string $path): ?array {
    // Remove any leading slash.
    $path = ltrim($path, '/');
    $pattern = '#(?:.*/)?styles/([^/]+)/(public|private)/(.+)$#';

    if (preg_match($pattern, $path, $matches)) {
      return [
        'style' => $matches[1],
        'scheme' => $matches[2],
        'path' => $matches[3],
      ];
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalFileUriFromStyleUrl(string $path): ?string {
    // Check cache first to avoid repeated parsing.
    if (isset($this->fileUriCache[$path])) {
      return $this->fileUriCache[$path];
    }

    $parsed = $this->parseImageStyleUrl($path);
    if (!$parsed) {
      // Cache null results to avoid re-parsing invalid paths.
      $this->fileUriCache[$path] = NULL;
      return NULL;
    }

    // Build the original URI by reconstructing from the derivative path.
    $original_path = $this->reconstructOriginalPath($parsed['path'], $parsed['scheme']);
    $result = $parsed['scheme'] . '://' . $original_path;

    // Store in cache.
    $this->fileUriCache[$path] = $result;

    return $result;
  }

  /**
   * Reconstructs the original file path from an image style derivative path.
   *
   * Handles cases where image style processing adds extensions (e.g., .webp,
   * .avif) to the derivative filename. This method detects multi-extension
   * files generically without hardcoding specific image formats.
   *
   * For single-extension webp/avif files, queries the database to find the
   * original file since the original extension is unknown (could be jpg, png,
   * etc.).
   *
   * Examples:
   * - Input: '2024-01/test_file.jpg.webp' -> Output: '2024-01/test_file.jpg'
   * - Input: 'photo.png.avif' -> Output: 'photo.png'
   * - Input: 'file.backup.2024.jpg.webp' -> Output: 'file.backup.2024.jpg'
   * - Input: 'simple.jpg' -> Output: 'simple.jpg'
   * - Input: 'test_file.webp' -> DB lookup -> 'test_file.jpg'
   *
   * Logic:
   * - If the filename has two consecutive image extensions (e.g., .jpg.webp),
   *   remove only the last one
   * - If the filename has a single webp/avif extension, query the database
   *   to find the original file
   * - Otherwise, keep the filename as-is
   *
   * @param string $derivative_path
   *   The path from the image style derivative URL.
   * @param string $scheme
   *   The URI scheme (e.g., 'public', 'private').
   *
   * @return string
   *   The reconstructed original file path.
   */
  protected function reconstructOriginalPath(string $derivative_path, string $scheme): string {
    $path_info = pathinfo($derivative_path);
    $dirname = $path_info['dirname'] ?? '';
    $basename = $path_info['basename'];

    // Split the basename by dots to analyze extensions.
    $parts = explode('.', $basename);

    // Need at least 3 parts to have double extensions (name.ext1.ext2).
    if (count($parts) >= 3) {
      $last_extension = strtolower(end($parts));
      $second_to_last = strtolower($parts[count($parts) - 2]);

      // Check if both the last and second-to-last parts are image extensions.
      if (in_array($last_extension, self::IMAGE_EXTENSIONS) &&
          in_array($second_to_last, self::IMAGE_EXTENSIONS)) {
        // Remove the last extension to get the original filename.
        array_pop($parts);
        $original_filename = implode('.', $parts);
      }
      else {
        // Not a double image extension, keep as-is.
        $original_filename = $basename;
      }
    }
    else {
      // Single extension or no extension.
      $original_filename = $basename;

      // Check if this is a single-extension webp/avif that needs DB lookup.
      if (count($parts) >= 2) {
        $extension = strtolower(end($parts));
        if (in_array($extension, ['webp', 'avif'])) {
          // Query database to find the original file.
          $db_result = $this->queryOriginalFileUri($derivative_path, $scheme);
          if ($db_result) {
            // Extract just the filename from the database result.
            $db_path_info = pathinfo($db_result);
            $original_filename = $db_path_info['basename'];
          }
        }
      }
    }

    // Reconstruct the full path.
    if (!empty($dirname) && $dirname !== '.') {
      return $dirname . '/' . $original_filename;
    }

    return $original_filename;
  }

  /**
   * Queries the database to find the original file URI.
   *
   * Used when a single-extension webp/avif file is encountered in an image
   * style derivative, and we need to discover the original file's actual
   * extension (which could be jpg, png, etc.).
   *
   * @param string $derivative_path
   *   The derivative file path (e.g., 'test_file.webp').
   * @param string $scheme
   *   The URI scheme (e.g., 'public', 'private').
   *
   * @return string|null
   *   The original file path if found, NULL otherwise.
   */
  protected function queryOriginalFileUri(string $derivative_path, string $scheme): ?string {
    // Build cache key from scheme and path without extension.
    $path_info = pathinfo($derivative_path);
    $dirname = $path_info['dirname'] ?? '';
    $filename_without_ext = $path_info['filename'];

    // Build the base URI for the LIKE query.
    $base_path = !empty($dirname) && $dirname !== '.'
      ? $dirname . '/' . $filename_without_ext
      : $filename_without_ext;
    $cache_key = $scheme . '://' . $base_path;

    // Check if we've already queried for this base path.
    if (isset($this->fileUriCache[$cache_key])) {
      return $this->fileUriCache[$cache_key];
    }

    // Query the file_managed table for files matching the base pattern.
    try {
      $query = $this->database->select('file_managed', 'fm')
        ->fields('fm', ['uri'])
        ->condition('uri', $this->database->escapeLike($cache_key) . '%', 'LIKE')
        ->range(0, 1);

      $result = $query->execute()->fetchField();

      if ($result) {
        // Extract just the path portion from the URI.
        $uri_parts = explode('://', $result, 2);
        if (count($uri_parts) === 2) {
          $this->fileUriCache[$cache_key] = $uri_parts[1];
          return $uri_parts[1];
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Error querying file URI: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    // Cache the null result to avoid repeated queries.
    $this->fileUriCache[$cache_key] = NULL;
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getFileFromUrl($path): ?FileInterface {
    $file = $this->entityTypeManager->getStorage('file')->loadByProperties(['uri' => $path]);
    if (empty($file)) {
      return NULL;
    }
    return reset($file);
  }

  /**
   * {@inheritdoc}
   */
  public function getMediaFileByEntityFile(FileInterface $file): ?MediaInterface {
    $media_types_with_tile_fields = $this->getMediaFieldTypeWithFileFields();
    if (empty($media_types_with_tile_fields)) {
      return NULL;
    }
    $media_query = $this->entityTypeManager->getStorage('media')->getQuery();
    $media_query->accessCheck(FALSE);
    $or_condition = $media_query->orConditionGroup();
    foreach ($media_types_with_tile_fields as $media_type_file_field) {
      $or_condition->condition($media_type_file_field['field_name'] . '.target_id', $file->id());
    }
    $media_query->condition($or_condition);
    $results = $media_query->execute();
    if (empty($results)) {
      return NULL;
    }
    return $this->entityTypeManager->getStorage('media')->load(reset($results));
  }

  /**
   * Get media types with file fields.
   *
   * @return array
   *   Array with the media type and the field name.
   */
  protected function getMediaFieldTypeWithFileFields() {
    $medias_type_reference_to_file = [];
    $media_types = $this->entityTypeManager->getStorage('media_type')->loadMultiple();
    foreach ($media_types as $media_type) {
      $fields = $this->entityFieldManager->getFieldDefinitions('media', (string) $media_type->id());
      foreach ($fields as $field) {
        if (in_array($field->getType(), ['file', 'image'])) {
          $medias_type_reference_to_file[] = [
            'field_name' => $field->getName(),
            'field_type' => $media_type->id(),
          ];
        }
      }
    }
    return $medias_type_reference_to_file;
  }

  /**
   * {@inheritdoc}
   */
  public function ifRedirectionForPath($path, $langcode, $count = 0) {
    $service_redirect = 'redirect.repository';
    // @phpstan-ignore-next-line
    $container = \Drupal::getContainer();
    if (!$container->has($service_redirect)) {
      return NULL;
    }
    $path = trim($path, '/');
    $redirect_repository = $container->get($service_redirect);
    try {

      if (!empty($langcode)) {
        $redirect_object = $redirect_repository->findMatchingRedirect($path, [], $langcode);
      }
      else {
        $redirect_object = $redirect_repository->findMatchingRedirect($path, []);
      }

    }
    catch (RedirectLoopException $e) {
      $this->logger->error($e->getMessage());
      return NULL;
    }
    if (!($redirect_object instanceof Redirect)) {
      return NULL;
    }

    $redirect = $redirect_object->getRedirect();
    $uri = $redirect['uri'] ?? NULL;
    if ($uri === NULL) {
      return NULL;
    }

    // Check if te redirection has a redirection and avoid infinite loop.
    if ($count > 5) {
      return $uri;
    }
    $filtered_uri = $this->handlePrefixFromRedirection($uri, $langcode);
    $new_redirection = $this->ifRedirectionForPath($filtered_uri, $langcode, $count++);

    return $new_redirection ?? $uri;
  }

  /**
   * Handle prefix from redirection.
   *
   * @param string $uri
   *   The uri.
   * @param string $langcode
   *   The langcode.
   *
   * @return string
   *   The uri.
   */
  public function handlePrefixFromRedirection($uri, $langcode = '') {
    $prefixes = ['internal:', 'entity:', $langcode];
    foreach ($prefixes as $prefix) {
      $uri = (string) preg_replace('/^' . $prefix . '/', '', $uri);
      $uri = trim($uri, '/');
    }
    return $uri;
  }

  /**
   * {@inheritdoc}
   */
  public function getPathWithoutLangPrefix($path) {
    $prefix = $this->getLangPrefixFromPath($path);
    if (empty($prefix)) {
      return $path;
    }

    // We ensure 2 structures: /langcode/path or /langcode.
    $processed_path = ltrim($path, '/');
    if (str_starts_with($processed_path, $prefix . '/')) {
      $path = substr($processed_path, strlen($prefix));
    }
    elseif ($processed_path === $prefix) {
      $path = '/';
    }

    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function getLangcodeFromPath($path): ?string {
    $result = NULL;
    if ($langcode_and_prefix = $this->getLangcodeAndPrefixFromPath($path)) {
      $result = $langcode_and_prefix['langcode'] ?? NULL;
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getLangPrefixFromPath($path): ?string {
    $result = NULL;
    if ($langcode_and_prefix = $this->getLangcodeAndPrefixFromPath($path)) {
      $result = $langcode_and_prefix['prefix'] ?? NULL;
    }
    return $result;
  }

  /**
   * Get the langcode and prefix from the path.
   *
   * @param string $path
   *   The path.
   *
   * @return array|null
   *   The langcode and prefix or NULL.
   */
  protected function getLangcodeAndPrefixFromPath($path): ?array {
    // If the prefixes ares empty, it is not a multilingual site, so
    // there are not langcode to return.
    if (empty($this->prefixes)) {
      return NULL;
    }
    $path = '/' . ltrim($path, '/');
    foreach ($this->prefixes as $langcode => $prefix) {
      if ($prefix && str_starts_with($path, '/' . $prefix . '/')) {
        return [
          'langcode' => $langcode,
          'prefix' => $prefix,
        ];
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function clearMeshAccountCache() {
    $this->meshAccount = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getMeshAccount(): DummyAccount {
    if (!$this->meshAccount) {
      $this->meshAccount = DummyAccount::create($this->entityTypeManager);

      // Get the configured analyzer account from settings.
      $config = $this->configFactory->get('entity_mesh.settings');
      $analyzer_account = $config->get('analyzer_account') ?: [
        'type' => 'anonymous',
        'roles' => NULL,
        'user_id' => NULL,
      ];

      switch ($analyzer_account['type']) {
        case 'anonymous':
          $this->meshAccount->setAsAnonymous();
          break;

        case 'authenticated':
          // Start with authenticated role.
          $roles = ['authenticated'];
          // Add any additional configured roles.
          if (!empty($analyzer_account['roles'])) {
            $roles = array_merge($roles, $analyzer_account['roles']);
          }
          $this->meshAccount->setRoles($roles);
          break;

        case 'user':
          if (!empty($analyzer_account['user_id'])) {
            try {
              $user = $this->entityTypeManager->getStorage('user')->load($analyzer_account['user_id']);
              if ($user) {
                $this->meshAccount->setRoles($user->getRoles());
                $this->meshAccount->setId((int) $user->id());
                $this->meshAccount->setPreferredLangcode($user->getPreferredLangcode());
              }
              else {
                $this->meshAccount->setAsAnonymous();
              }
            }
            catch (\Exception $e) {
              // Error loading user, fallback to anonymous.
              $this->meshAccount->setAsAnonymous();
            }
          }
          else {
            // No user ID configured, fallback to anonymous.
            $this->meshAccount->setAsAnonymous();
          }
          break;

        default:
          // Unknown type, fallback to anonymous.
          $this->meshAccount->setAsAnonymous();
          break;
      }
    }

    return $this->meshAccount;
  }

  /**
   * {@inheritdoc}
   */
  public function checkViewAccessEntity(EntityInterface $entity): bool {
    $mesh_account = $this->getMeshAccount();
    return $entity->access('view', $mesh_account);
  }

}
