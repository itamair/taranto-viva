<?php

namespace Drupal\entity_mesh;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Access\AccessManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\entity_mesh\Language\LanguageNegotiatorSwitcher;

/**
 * Service description.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class EntityRender extends Entity {

  /**
   * The Renderer manager.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The AccountSwitcher manager.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected $accountSwitcher;

  /**
   * Switch to the language of rendered entities.
   *
   * @var \Drupal\entity_mesh\Language\LanguageNegotiatorSwitcher
   */
  protected LanguageNegotiatorSwitcher $languageNegotiatorSwitcher;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Access manager.
   *
   * @var \Drupal\Core\Access\AccessManager
   */
  protected AccessManager $accessManager;

  /**
   * Theme switcher.
   *
   * @var \Drupal\entity_mesh\ThemeSwitcherInterface
   */
  protected ThemeSwitcherInterface $themeSwitcher;

  /**
   * Cache for entity DOM to avoid redundant rendering.
   *
   * This cache prevents the same entity from being rendered multiple times
   * during a single request. This is particularly useful when
   * countEntityLinks() is called before setTargetsInSourceFromEntityRender(),
   * as both methods need the same DOM. The cache is cleared after
   * createSourceFromEntity() to free memory.
   *
   * @var array
   */
  protected array $domCache = [];

  /**
   * Constructs a Menu object.
   *
   * @param \Drupal\entity_mesh\RepositoryInterface $entity_mesh_repository
   *   Entity Mesh repository.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity repository.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer manager.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $account_switcher
   *   The AccountSwitcher manager.
   * @param \Drupal\entity_mesh\Language\LanguageNegotiatorSwitcher $language_negotiator_switcher
   *   The language negotiator switcher service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler.
   * @param \Drupal\Core\Access\AccessManager $access_manager
   *   Access manager.
   * @param \Drupal\entity_mesh\ThemeSwitcher $theme_switcher
   *   The theme switcher service.
   * @param \Drupal\entity_mesh\TrackerManagerInterface $tracker_manager
   *   The tracker manager.
   */
  public function __construct(
    RepositoryInterface $entity_mesh_repository,
    EntityTypeManagerInterface $entity_type_manager,
    LanguageManagerInterface $language_manager,
    ConfigFactoryInterface $config_factory,
    RendererInterface $renderer,
    AccountSwitcherInterface $account_switcher,
    LanguageNegotiatorSwitcher $language_negotiator_switcher,
    ModuleHandlerInterface $module_handler,
    AccessManager $access_manager,
    ThemeSwitcher $theme_switcher,
    TrackerManagerInterface $tracker_manager,
  ) {
    parent::__construct($entity_mesh_repository, $entity_type_manager, $language_manager, $config_factory, $tracker_manager);
    $this->renderer = $renderer;
    $this->accountSwitcher = $account_switcher;
    $this->type = 'entity_render';
    $this->languageNegotiatorSwitcher = $language_negotiator_switcher;
    $this->moduleHandler = $module_handler;
    $this->accessManager = $access_manager;
    $this->themeSwitcher = $theme_switcher;
  }

  /**
   * {@inheritDoc}
   */
  public function createSourceFromEntity(EntityInterface $entity): ?SourceInterface {
    $source = $this->createBasicSourceFromEntity($entity);
    if (!$source instanceof SourceInterface) {
      return NULL;
    }
    $this->setTargetsInSourceFromEntityRender($entity, $source);

    // Clear the DOM cache after processing the entity to free memory.
    $this->clearDomCache();

    return $source;
  }

  /**
   * Clear the DOM cache.
   *
   * This should be called after processing entities to free memory,
   * especially important in batch operations.
   */
  public function clearDomCache(): void {
    $this->domCache = [];
  }

  /**
   * Set the targets in the source from the links in the entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param \Drupal\entity_mesh\SourceInterface $source
   *   The source.
   */
  protected function setTargetsInSourceFromEntityRender(EntityInterface $entity, SourceInterface $source) {
    $langcode = $entity->language()->getId();

    try {
      $dom = $this->getFullEntityDom($entity, $langcode);
    }
    catch (\Exception $e) {
      $this->entityMeshRepository->getLogger()->error('Error getting the entity DOM (entity=:entity, id=:id) = :message', [
        ':entity' => $entity->getEntityTypeId(),
        ':id' => $entity->id(),
        ':message' => $e->getMessage(),
      ]);
      return;
    }

    // Get and save target links.
    $target_links = $this->getTargetLinks($dom);

    if ($this->config->get('entity_mesh.settings')->get('debug')) {
      $this->entityMeshRepository->getLogger()->debug('Links to process in this node (entity=:entity, id=:id, lang=:lang): :link_numbers', [
        ':entity' => $entity->getEntityTypeId(),
        ':id' => $entity->id(),
        ':lang' => $langcode,
        ':link_numbers' => count($target_links),
      ]);
    }

    foreach ($target_links as $target_link) {
      $this->setTargetFromHref($target_link, $source);
    }

    // Get and save iframes.
    $iframe_links = $this->getIframeLinks($dom);
    foreach ($iframe_links as $iframe_link) {
      $this->setTargetFromIframe($iframe_link, $source);
    }

    // Get and save images.
    $image_links = $this->getImageLinks($dom);
    foreach ($image_links as $image_link) {
      $this->setTargetFromImage($image_link, $source);
    }
  }

  /**
   * Count total links in an entity without processing them.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to count links from.
   *
   * @return int
   *   The total number of links (hrefs + iframes).
   */
  public function countEntityLinks(EntityInterface $entity): int {
    $langcode = $entity->language()->getId();

    try {
      $dom = $this->getFullEntityDom($entity, $langcode);
    }
    catch (\Exception $e) {
      // If we can't get the DOM, assume no links.
      return 0;
    }

    // Count target links and iframe links.
    $target_links = $this->getTargetLinks($dom);
    $iframe_links = $this->getIframeLinks($dom);
    $image_links = $this->getImageLinks($dom);

    // Get current entity's URL to exclude self-references.
    $entity_url = '';
    if ($entity->hasLinkTemplate('canonical')) {
      try {
        $entity_url = $entity->toUrl('canonical')->toString();
        $target_links = array_filter($target_links, function ($link) use ($entity_url) {
          // Exclude self-references.
          return $link !== $entity_url;
        });
      }
      catch (\Exception $e) {
        // If we can't get the URL, continue without filtering.
      }
    }

    return count($target_links) + count($iframe_links) + count($image_links);
  }

  /**
   * Get target links.
   */
  protected function getTargetLinks($dom) {
    $links = $dom->getElementsByTagName('a');
    $target_links = [];
    foreach ($links as $link) {
      $href = trim(strip_tags($link->getAttribute('href')));
      if (!empty($href) && $href[0] != "#") {
        // @todo integrate nodeValue, note same link can appear several times
        // with different or same anchors
        // 'target_anchor' => strip_tags($link->nodeValue),
        $target_links[] = $href;
      }
    }

    // Remove duplicate hrefs.
    return array_unique($target_links);
  }

  /**
   * Get the iframes links.
   */
  protected function getIframeLinks($dom) {
    $iframes = $dom->getElementsByTagName('iframe');
    $iframe_links = [];
    foreach ($iframes as $iframe) {
      $src = trim(strip_tags($iframe->getAttribute('src')));
      if (!empty($src) && $src[0] != "#") {
        $iframe_links[] = $src;
      }
    }

    // Remove duplicate hrefs.
    return array_unique($iframe_links);
  }

  /**
   * Get image links from the DOM.
   *
   * Extracts src and srcset attributes from <img> tags and <source> tags,
   * including support for lazy-loaded images and responsive images.
   * Uses associative array for efficient deduplication.
   *
   * @param \DOMDocument $dom
   *   The DOM document.
   *
   * @return array
   *   Array of unique image source URLs.
   */
  protected function getImageLinks($dom) {
    // Use associative array for automatic deduplication and better performance.
    $image_links_map = [];

    // Extract from <img> tags.
    $images = $dom->getElementsByTagName('img');
    foreach ($images as $image) {
      // Check src attribute.
      $src = trim(strip_tags($image->getAttribute('src')));
      if (empty($src)) {
        // Check data-src for lazy-loaded images.
        $src = trim(strip_tags($image->getAttribute('data-src')));
      }

      if (!empty($src) && $src[0] != "#" && !str_starts_with($src, 'data:')) {
        $image_links_map[] = $src;
      }
    }

    return $image_links_map;
  }

  /**
   * Get the full entity DOM.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to render.
   * @param string $langcode
   *   The language code.
   *
   * @return \DOMDocument
   *   The DOM document.
   *
   * @SuppressWarnings(PHPMD.ErrorControlOperator)
   */
  protected function getFullEntityDom(EntityInterface $entity, string $langcode) {
    // Generate cache key based on entity type, ID, and language.
    $cache_key = $entity->getEntityTypeId() . ':' . $entity->id() . ':' . $langcode;

    // Check if DOM is already cached.
    if (isset($this->domCache[$cache_key])) {
      return $this->domCache[$cache_key];
    }
    // Switch to the default theme in case the admin theme is enabled.
    $previous_theme = $this->themeSwitcher->switchToDefault();

    $this->accountSwitcher->switchTo($this->entityMeshRepository->getMeshAccount());
    // Find LINKs from rendered entity page output.
    // Switch to the anonymous user:
    // @todo allow to configure default user by settings form.
    // Switch system to the entity language so the entity is fully rendered
    // in the specific language.
    $this->languageNegotiatorSwitcher->switchLanguage($entity->language());

    // Load all modules before rendering.
    if (!$this->moduleHandler->isLoaded()) {
      $this->moduleHandler->loadAll();
    }

    try {
      // Render entity HTML output:
      $view_mode = 'full';
      $view_builder = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId());
      $pre_render = $view_builder->view($entity, $view_mode, $langcode);

      $render_output = DeprecationHelper::backwardsCompatibleCall(
        currentVersion: \Drupal::VERSION,
        deprecatedVersion: '10.3',
        currentCallable: fn() => $this->renderer->renderInIsolation($pre_render),
        deprecatedCallable: fn() => $this->renderer->renderPlain($pre_render),
      );

      // Switches back to the current language:
      $this->languageNegotiatorSwitcher->switchBack();
      // Switch back to the current user:
      $this->accountSwitcher->switchBack();
      // Restore the original theme.
      $this->themeSwitcher->switchBack($previous_theme);

      // Parse the HTML.
      $dom = new \DOMDocument();
      // The @ is used to suppress any parsing errors that will be thrown
      // if the $html string isn't valid XHTML.
      // Fix UTF-8 encoding issues with diacritical marks
      // by explicitly setting encoding.
      @$dom->loadHTML('<?xml encoding="UTF-8">' . $render_output, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

      // Cache the DOM before returning.
      $this->domCache[$cache_key] = $dom;

      return $dom;
    }
    catch (\Exception $e) {
      // Ensure we switch everything back in case of an error.
      $this->languageNegotiatorSwitcher->switchBack();
      $this->accountSwitcher->switchBack();
      $this->themeSwitcher->switchBack($previous_theme);
      throw $e;
    }
  }

  /**
   * Set the target from iframe.
   *
   * @param string $href
   *   Href.
   * @param SourceInterface $source
   *   Source.
   */
  protected function setTargetFromIframe(string $href, SourceInterface $source) {
    /** @var \Drupal\entity_mesh\TargetInterface $target */
    $target = $this->entityMeshRepository->instanceEmptyTarget();
    $target->processHrefAndSetComponents($href);
    $target->setCategory('iframe');
    $source->addTarget($target);
  }

  /**
   * Set the target from image source.
   *
   * Processes image src attributes and creates target records.
   * Handles image style URLs and resolves to media entities.
   *
   * @param string $src
   *   Image source URL.
   * @param \Drupal\entity_mesh\SourceInterface $source
   *   Source object.
   */
  protected function setTargetFromImage(string $src, SourceInterface $source) {
    /** @var \Drupal\entity_mesh\TargetInterface $target */
    $target = $this->entityMeshRepository->instanceEmptyTarget();

    $target->processHrefAndSetComponents($src);
    $target->setCategory('link');

    if ($target->getLinkType() == 'internal' && $this->ifProcessInternalTarget()) {
      $this->setDataTargetIfFileUrl($target);
      $this->setBundleInTarget($target);

      // Check that the target is not the source to avoid circular references.
      if ($source->getSourceEntityType() == $target->getEntityType()
        && $source->getSourceEntityId() == $target->getEntityId()) {
        return;
      }
    }

    if ($this->shouldRegisterTarget($target)) {
      $source->addTarget($target);
    }
  }

  /**
   * Set the target from the href.
   *
   * @param string $href
   *   Href.
   * @param SourceInterface $source
   *   Source.
   */
  protected function setTargetFromHref(string $href, SourceInterface $source) {
    /** @var \Drupal\entity_mesh\TargetInterface $target */
    $target = $this->entityMeshRepository->instanceEmptyTarget();

    $target->processHrefAndSetComponents($href);
    $target->setCategory('link');

    if ($target->getLinkType() == 'internal' && $this->ifProcessInternalTarget()) {
      $this->processInternalHref($target, $source->getSourceEntityLangcode());

      // Check that the target is not the source to avoid circular references.
      if ($source->getSourceEntityType() == $target->getEntityType()
        && $source->getSourceEntityId() == $target->getEntityId()) {
        return;
      }
    }

    if ($this->shouldRegisterTarget($target)) {
      $source->addTarget($target);
    }

  }

  /**
   * Process internal href.
   *
   * @param \Drupal\entity_mesh\TargetInterface $target
   *   The target.
   * @param string|null $fallback_langcode
   *   Optional fallback langcode from the source entity. Used when the URL
   *   has no language prefix, which is required for redirect lookups to
   *   work correctly (see issue #3570211).
   */
  protected function processInternalHref(
    TargetInterface $target,
    ?string $fallback_langcode = NULL,
  ) {
    $path = (string) $target->getPath();

    if ($path === '') {
      return;
    }

    $alias = $this->entityMeshRepository->getPathWithoutLangPrefix($path);
    $langcode_from_path = $this->entityMeshRepository->getLangcodeFromPath($path);
    // Resolve the target langcode using a two-part strategy:
    // 1. Use the langcode extracted from the URL path prefix when present
    //    (e.g. /es/node/1 yields 'es').
    // 2. Fall back to the source entity's langcode when the URL has no
    //    language prefix. This is required so that redirect lookups can
    //    match the correct redirect record (issue #3570211).
    // When neither the path nor the fallback provides a langcode, the target
    // langcode remains NULL and access is checked against the default
    // translation. When a langcode is set but the entity lacks that
    // translation, accessCheckTarget() also falls back to the default
    // translation instead of denying access outright.
    $target->setEntityLangcode($langcode_from_path ?? $fallback_langcode);

    $found_data = $this->setDataIfRedirection($alias, $target);

    // Get the info from alias.
    // This method is quicker than using the router service,
    // so firstly apply this system.
    if (!$found_data) {
      $found_data = $this->setDataTargetFromAliasIfExists($alias, $target);
    }

    // If this method not found the data, use the router service.
    if (!$found_data) {
      $this->setDataTargetFromRoute($target);
    }

    // If the target is set as link broken maybe is because is a file url.
    if ($target->getSubcategory() === 'broken-link') {
      $this->setDataTargetIfFileUrl($target);
    }

    $this->setBundleInTarget($target);

    if (!($this->accessCheckTarget($target))) {
      $target->setSubcategory('access-denied-link');
    }

  }

  /**
   * Set the bundle in the target.
   *
   * @param \Drupal\entity_mesh\TargetInterface $target
   *   The target.
   */
  protected function setBundleInTarget(TargetInterface $target) {
    if ($target->getEntityType() === NULL || $target->getEntityId() === NULL) {
      return;
    }

    try {
      $storage = $this->entityTypeManager->getStorage($target->getEntityType());
    }
    catch (PluginNotFoundException $e) {
      return;
    }

    $entity = $storage->load($target->getEntityId());

    if (!$entity instanceof EntityInterface || empty($entity->bundle())) {
      return;
    }

    $target->setEntityBundle($entity->bundle());
  }

  /**
   * Get the data if it is a redirection.
   *
   * @param string $alias
   *   The alias.
   * @param TargetInterface $target
   *   The target.
   *
   * @return bool
   *   If is a redirection.
   */
  protected function setDataIfRedirection(string &$alias, TargetInterface $target): bool {
    $langcode = $target->getEntityLangcode();
    // Check if is a redirected link.
    $uri = $this->entityMeshRepository->ifRedirectionForPath($alias, $langcode);
    if ($uri === NULL) {
      return FALSE;
    }

    $type = '';
    $target->setSubcategory('redirected-link');

    // Check if it starts with "internal:".
    if (str_starts_with($uri, "internal:")) {
      $uri = substr($uri, strlen("internal:"));
      $type = 'internal';
    }
    elseif (str_starts_with($uri, "entity:")) {
      $uri = substr($uri, strlen("entity:"));
      $type = 'entity';
    }
    elseif (str_starts_with($uri, "http")) {
      $target->setLinkType('external');
      $target->setPath('External redirection: ' . $uri);
      return TRUE;
    }

    $uri = $this->entityMeshRepository->getPathWithoutLangPrefix($uri);

    if ($type === '') {
      $alias = $uri;
      return FALSE;
    }

    // The internal can be an alias.
    if ($type === 'internal' && $possible_alias = $this->getPathFromAliasTables($uri, $langcode)) {
      $uri = $possible_alias->path;
    }

    return $this->processInternalPaths($uri, $target);
  }

  /**
   * Set data about the target from alias if exists.
   *
   * @param string $alias
   *   Alias.
   * @param TargetInterface $target
   *   Target.
   *
   * @return bool
   *   If this method found the data.
   */
  protected function setDataTargetFromAliasIfExists(string $alias, TargetInterface $target): bool {
    // Get the info from alias.
    $langcode = $target->getEntityLangcode();
    if ($this->moduleHandler->moduleExists('path_alias')) {
      $record = $this->getPathFromAliasTables($alias, $langcode);
      if ($record) {
        return $this->processInternalPaths($record->path, $target);
      }
    }
    return FALSE;
  }

  /**
   * Process internal paths.
   *
   * @param string $path
   *   The path.
   * @param TargetInterface $target
   *   The target.
   *
   * @return bool
   *   If the path is processed.
   */
  protected function processInternalPaths(string $path, TargetInterface $target) {
    $path = explode('/', ltrim($path, '/'));
    if (count($path) < 2) {
      return FALSE;
    }
    if ($path[0] === 'taxonomy') {
      $path[0] = 'taxonomy_term';
    }

    if (isset($path[2]) && is_numeric($path[2])) {
      $target->setEntityType($path[0]);
      $target->setEntityId((string) $path[2]);
      return TRUE;
    }

    $target->setEntityType($path[0]);
    $target->setEntityId($path[1] ?? '');
    return TRUE;
  }

  /**
   * Set data about the target from route.
   *
   * @param TargetInterface $target
   *   The target.
   */
  protected function setDataTargetFromRoute($target) {
    if (empty($target->getPath())) {
      return;
    }

    // Get the path from target.
    $path = $target->getPath();

    try {
      // @phpstan-ignore-next-line
      $route_match = \Drupal::service('router.no_access_checks')->match($path);
    }
    catch (\Exception $e) {
      $target->setSubcategory('broken-link');
      return;
    }

    if (empty($route_match['_route'])) {
      $target->setSubcategory('broken-link');
      return;
    }

    // Check if the route match belongs to a entity route.
    $entity = $this->checkAndGetEntityFromEntityRoute($route_match);
    if ($entity instanceof EntityInterface) {
      $target->setEntityType($entity->getEntityTypeId());
      $target->setEntityId((string) $entity->id());
      return;
    }

    // Check if the route match belongs to a view route.
    if (isset($route_match['view_id']) && isset($route_match['display_id'])) {
      $target->setEntityType('view');
      $target->setEntityId($route_match['view_id'] . '.' . $route_match['display_id']);
    }

  }

  /**
   * Set data target if file url.
   *
   * @param TargetInterface $target
   *   The target.
   */
  protected function setDataTargetIfFileUrl($target) {
    // Check if the path is a file.
    $path = $this->entityMeshRepository->getPathFromFileUrl($target->getPath() ?? '');
    if (!$path) {
      return;
    }

    // Try get the url from the path.
    $file = $this->entityMeshRepository->getFileFromUrl($path);
    if (empty($file)) {
      return;
    }

    $target->setSubcategory($target->getCategory() ?? '');

    // Try get the media that referred to the file.
    $media = $this->entityMeshRepository->getMediaFileByEntityFile($file);

    // If we do not have a media, set information of the entity file.
    if (!$media) {
      $target->setEntityType($file->getEntityTypeId());
      $target->setEntityId((string) $file->id());
      return;
    }

    $target->setEntityType($media->getEntityTypeId());
    $target->setEntityBundle($media->bundle());
    $target->setEntityId((string) $media->id());
  }

  /**
   * Get the path form alias.
   *
   * @param string $alias
   *   The alias.
   * @param string $langcode
   *   The language code.
   *
   * @return mixed|null
   *   Return the path.
   */
  protected function getPathFromAliasTables(string $alias, string $langcode) {
    $query = $this->entityMeshRepository->getDatabaseService()->select('path_alias', 'pa');
    $query->fields('pa', ['path']);
    $or = $query->orConditionGroup()
      ->condition('alias', $alias)
      ->condition('path', $alias);
    $query->condition($or);
    if (!empty($langcode)) {
      $query->condition('langcode', $langcode);
    }
    $result = $query->execute();
    if (!$result instanceof StatementInterface) {
      return NULL;
    }
    return $result->fetchObject();
  }

  /**
   * {@inheritDoc}
   */
  public function deleteItem(string $entity_type, string $entity_id, string $type = '') {
    parent::deleteItem('entity_render', $entity_type, $entity_id);
  }

  /**
   * Access check uri.
   *
   * @param \Drupal\entity_mesh\Target $target
   *   Uri to test.
   *
   * @return bool
   *   Access allowed or not.
   */
  public function accessCheckTarget(Target $target) {
    // Only check internal targets.
    if ($target->getLinkType() !== 'internal'
      || empty($target->getEntityType())
      || empty($target->getEntityId())) {
      return TRUE;
    }

    if ($target->getEntityType() === 'view') {
      $route_view = 'view.' . $target->getEntityId();

      // Check access.
      return $this->accessManager->checkNamedRoute($route_view, [], $this->entityMeshRepository->getMeshAccount());

    }

    try {
      $storage = $this->entityTypeManager->getStorage($target->getEntityType());
    }
    catch (PluginNotFoundException) {
      return TRUE;
    }

    $entity = $storage->load($target->getEntityId());

    if (!$entity) {
      // Deny access if the entity does not exist.
      return FALSE;
    }

    // Special handling for webform entities.
    if ($target->getEntityType() === 'webform') {
      return $this->checkWebformAccess($entity);
    }

    // For non-translatable entities or when no specific langcode is requested,
    // check access on the entity as loaded (its default translation).
    // For translatable entities with a specific langcode, attempt to load
    // the requested translation. If that translation does not exist, fall
    // back to checking access on the default translation rather than
    // denying access, since the entity itself is accessible.
    if (empty($target->getEntityLangcode())
      || !($entity instanceof TranslatableInterface)
      || !($entity->isTranslatable())) {
      return $this->entityMeshRepository->checkViewAccessEntity($entity);
    }

    if (!($entity->hasTranslation($target->getEntityLangcode()))) {
      return $this->entityMeshRepository->checkViewAccessEntity($entity);
    }

    $translation = $entity->getTranslation($target->getEntityLangcode());

    return $this->entityMeshRepository->checkViewAccessEntity($translation);
  }

  /**
   * Check access for webform entities.
   *
   * Webforms use a specific access control system based on access rules.
   * This method checks if the mesh account can create submissions for the
   * webform, which is the equivalent to viewing/accessing the webform for
   * anonymous users.
   *
   * @param \Drupal\Core\Entity\EntityInterface $webform
   *   The webform entity.
   *
   * @return bool
   *   TRUE if access is granted, FALSE otherwise.
   */
  protected function checkWebformAccess(EntityInterface $webform) {
    $mesh_account = $this->entityMeshRepository->getMeshAccount();

    // Check if webform module is available and entity is a webform.
    if (!$this->moduleHandler->moduleExists('webform')) {
      return FALSE;
    }

    // Get the webform access rules manager service.
    try {
      /** @var \Drupal\webform\WebformAccessRulesManagerInterface $access_rules_manager
       * @phpstan-ignore-next-line
       */
      $access_rules_manager = \Drupal::service('webform.access_rules_manager');
    }
    catch (\Exception $e) {
      // If the service is not available, fall back to standard access check.
      return $webform->access('view', $mesh_account);
    }

    // Check if the mesh account can create submissions (which means
    // they can access/view the webform).
    return $access_rules_manager->checkWebformAccess('create', $mesh_account, $webform);
  }

}
