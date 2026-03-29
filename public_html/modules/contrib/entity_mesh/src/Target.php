<?php

namespace Drupal\entity_mesh;

use Drupal\Component\Utility\UrlHelper;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class Target to instance target objects.
 *
 * @package Drupal\entity_mesh
 */
class Target implements TargetInterface {

  use HelperTrait;

  /**
   * Category.
   *
   * @var string|null
   */
  protected $category;

  /**
   * Category.
   *
   * @var string|null
   */
  protected $subCategory;

  /**
   * Hash ID.
   *
   * @var string|null
   */
  protected $hashId;

  /**
   * Current domain.
   *
   * @var string
   */
  protected $currentDomain = '';

  /**
   * Base path.
   *
   * @var string
   */
  protected $basePath = '';

  /**
   * Href property.
   *
   * @var string|null
   */
  protected $targetHref;

  /**
   * Path property.
   *
   * @var string|null
   */
  protected $targetPath;

  /**
   * Scheme property.
   *
   * @var string|null
   */
  protected $targetScheme;

  /**
   * Link type property.
   *
   * @var string|null
   */
  protected $targetLinkType;

  /**
   * Host property.
   *
   * @var string|null
   */
  protected $targetHost;

  /**
   * Entity Type.
   *
   * @var string|null
   */
  protected $targetEntityType;

  /**
   * Entity Bundle.
   *
   * @var string|null
   */
  protected $targetEntityBundle;

  /**
   * Entity ID.
   *
   * @var string|null
   */
  protected $targetEntityId;

  /**
   * Langcode.
   *
   * @var string|null
   */
  protected $targetEntityLangcode;

  /**
   * Title.
   *
   * @var string|null
   */
  protected $title;

  /**
   * Whether self domain URLs should be considered internal.
   *
   * @var bool
   */
  protected $selfDomainInternal;

  /**
   * {@inheritdoc}
   */
  public static function create(RequestStack $request_stack, bool $self_domain_internal = TRUE): Target {
    $target = new self();
    $current_request = $request_stack->getCurrentRequest();
    if ($current_request) {
      $target->currentDomain = $current_request->getHost();
      $target->basePath = $current_request->getBasePath();
    }
    $target->selfDomainInternal = $self_domain_internal;
    return $target;
  }

  /**
   * {@inheritdoc}
   */
  public function getCategory(): ?string {
    return $this->category ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setCategory($category) {
    $this->category = $category;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubcategory(): ?string {
    return $this->subCategory ?? $this->getCategory();
  }

  /**
   * {@inheritdoc}
   */
  public function setSubcategory($sub_category) {
    $this->subCategory = $sub_category;
  }

  /**
   * {@inheritdoc}
   */
  public function getHref(): ?string {
    return $this->targetHref ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setHref(?string $href) {
    $this->targetHref = $href;
  }

  /**
   * {@inheritdoc}
   */
  public function processHrefAndSetComponents(string $href) {
    // Decode the URL if it is encoded.
    if ($href !== rawurldecode($href)) {
      $href = rawurldecode($href);
    }

    $this->setHref($this->sanitizeUrl($href));

    $validation_result = $this->isValidUrl($href);

    // If it is an invalid link we check that invalid one.
    // We exclude the asynchronous urls.
    if ($validation_result === 0 && !preg_match('/[?&]_format=json/', $href)) {
      $this->setCategory('link');
      $this->setSubcategory('invalid-url');
      return;
    }

    // If it is an invalid tel number.
    if ($validation_result === 3) {
      $this->setCategory('link');
      $this->setSubcategory('invalid-tel');
      return;
    }

    $href_components = parse_url($href);

    $this->setIfExternalOrInternal($href_components);

    // If is internal, we need to set the path.
    if ($this->getLinkType() === 'internal') {
      $path = $href_components['path'] ?? '';
      if (!empty($path)) {
        $path = $this->sanitizeUrl($path);

        // Remove base path from the URL if Drupal is installed
        // in a subdirectory.
        if (!empty($this->basePath) && strpos($path, $this->basePath) === 0) {
          $path = substr($path, strlen($this->basePath));
          // Ensure path starts with /.
          $path = '/' . ltrim($path, '/');
        }
      }
      $this->setPath($path);
    }

    // Populate the rest of target url parts.
    $this->setHost($href_components['host'] ?? '');
    $this->setScheme($href_components['scheme'] ?? '');
  }

  /**
   * Set if the target is external or internal.
   *
   * @param array $href_components
   *   Href components, result of parse_url.
   */
  protected function setIfExternalOrInternal(array $href_components) {
    // Cover the case of the href being internal but absolute.
    if ($this->selfDomainInternal &&
      isset($href_components['host']) &&
      $href_components['host'] === $this->currentDomain) {
      $this->setLinkType('internal');
      return;
    }

    // This is internal because the relative path has not defined
    // schema and host.
    if (empty($href_components['scheme']) && empty($href_components['host'])) {
      $this->setLinkType('internal');
      return;
    }
    $this->setLinkType('external');
  }

  /**
   * {@inheritdoc}
   */
  public function getHost(): ?string {
    return $this->targetHost ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setHost(?string $host) {
    $this->targetHost = $host;
  }

  /**
   * {@inheritdoc}
   */
  public function getScheme(): ?string {
    return $this->targetScheme ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setScheme(?string $scheme) {
    $this->targetScheme = $scheme;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType(): ?string {
    return $this->targetEntityType ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityType(?string $entity_type) {
    $this->targetEntityType = $entity_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityId(): ?string {
    return $this->targetEntityId ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityId(?string $entity_id) {
    $this->targetEntityId = $entity_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityLangcode(): string {
    return $this->targetEntityLangcode ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityLangcode(?string $entity_langcode) {
    $this->targetEntityLangcode = $entity_langcode;
  }

  /**
   * {@inheritdoc}
   */
  public function getLinkType(): ?string {
    return $this->targetLinkType ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setLinkType(?string $link_type) {
    return $this->targetLinkType = $link_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getPath(): ?string {
    return $this->targetPath ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setPath(?string $path) {
    $this->targetPath = $path;
  }

  /**
   * {@inheritdoc}
   */
  public function getHashId(): ?string {
    return $this->hashId ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityBundle(): ?string {
    return $this->targetEntityBundle ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityBundle(string $entity_bundle) {
    $this->targetEntityBundle = $entity_bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(): ?string {
    return $this->title ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle(string $title) {
    $this->title = $title;
  }

  /**
   * {@inheritdoc}
   */
  public function setHashId() {
    $string_to_hash = '';
    if ($this->getEntityType() && $this->getEntityId()) {
      $string_to_hash = $this->getEntityType() . $this->getEntityId();
    }
    elseif ($this->getHref()) {
      $string_to_hash = $this->getHref();
    }

    if ($string_to_hash) {
      $this->hashId = $this->generateHash((string) $string_to_hash);
    }
  }

  /**
   * Validates if the given href is a valid HTTP/HTTPS URL.
   *
   * Uses UrlHelper::isValid() to check if the URL is well-formed and
   * restricts to HTTP/HTTPS schemes only. Non-HTTP schemes like tel:,
   * mailto:, javascript:, etc. are considered invalid for link tracking.
   *
   * @param string $href
   *   The href to validate.
   *
   * @return int
   *   Validation result:
   *    - 0: it is not valid
   *    - 1: it is valid
   *    - 2: Validation does not apply
   *    - 3: Invalid tel number
   */
  protected function isValidUrl(string $href): int {
    // Empty hrefs are not valid URLs.
    if (empty(trim($href))) {
      return 0;
    }

    // Check for tel: scheme and validate phone number format.
    // Follows RFC 3966: https://www.rfc-editor.org/rfc/rfc3966
    if (stripos($href, 'tel:') === 0) {
      // Extract phone number part after "tel:".
      $phone_number = substr($href, 4);
      $phone_number = trim($phone_number);

      // Phone number is invalid if empty.
      if (empty($phone_number)) {
        return 3;
      }

      // Validate tel: phone number format.
      // Accepts any number with digits and visual separators (- . ( )),
      // optionally prefixed with + or 00 for international format.
      // Optional parameters: ;phone-context=<value> and/or ;ext=<digits>.
      // Deliberately lenient to avoid false positives on valid tel: links
      // that don't strictly follow RFC 3966 (e.g. local numbers without
      // phone-context, which are common in real-world usage).
      $number = '(\+|00)?[0-9][0-9\-\.\(\)]*';
      $phone_context = '(;phone-context=[a-zA-Z0-9.\-+]+)?';
      $extension = '(;ext=[0-9]+)?';

      $pattern = '/^(' . $number . ')' . $phone_context . $extension . '$/';

      if (!preg_match($pattern, $phone_number)) {
        return 3;
      }

      // Valid tel: format.
      return 2;
    }

    // Check for other non-HTTP(S) schemes that should be excluded.
    $excluded_schemes = ['mailto:', 'javascript:', 'data:', 'ftp:', 'fax:'];
    foreach ($excluded_schemes as $scheme) {
      if (stripos($href, $scheme) === 0) {
        return 2;
      }
    }

    $absolute = TRUE;
    if (strpos($href, '/') === 0 || strpos($href, '//') === 0) {
      $absolute = FALSE;
    }

    // Encode problematic characters before validation for all URLs.
    // Replace spaces with %20.
    $href_to_validate = str_replace(' ', '%20', $href);
    // Encode non-ASCII characters.
    $href_to_validate = preg_replace_callback('/[^\x20-\x7E]/', fn($m) => rawurlencode($m[0]), $href_to_validate);

    // Use UrlHelper::isValid() with HTTP validation.
    return (int) UrlHelper::isValid($href_to_validate, $absolute);
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return [
      'category' => $this->getCategory(),
      'subcategory' => $this->getSubcategory(),
      'target_link_type' => $this->getLinkType(),
      'target_href' => $this->getHref(),
      'target_path' => $this->getPath(),
      'target_scheme' => $this->getScheme(),
      'target_host' => $this->getHost(),
      'target_entity_type' => $this->getEntityType(),
      'target_entity_id' => $this->getEntityId(),
      'target_entity_langcode' => $this->getEntityLangcode(),
      'target_hash_id' => $this->getHashId(),
      'target_entity_bundle' => $this->getEntityBundle(),
      'target_title' => $this->getTitle(),
    ];
  }

}
