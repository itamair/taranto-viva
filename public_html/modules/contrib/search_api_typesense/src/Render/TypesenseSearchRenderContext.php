<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Render;

use Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend;

/**
 * Context object for Typesense search rendering.
 *
 * This value object contains all the configuration and context data needed
 * to render a Typesense search interface, whether from a block or controller.
 */
class TypesenseSearchRenderContext {

  /**
   * Constructs a new TypesenseSearchRenderContext.
   *
   * @param \Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend $backend
   *   The Typesense backend instance.
   * @param \Drupal\search_api\IndexInterface[] $indexes
   *   Array of Search API indexes to render.
   * @param string $apiKey
   *   The API key to use (search-only or admin key).
   * @param string $blockUuid
   *   The UUID of the search block instance.
   * @param int $hitsPerPage
   *   Number of items to display per page.
   * @param bool $debug
   *   Whether to enable debug mode.
   * @param string|null $currentLanguage
   *   Current language code for multilingual sites.
   * @param array<string, mixed> $additionalSettings
   *   Additional JavaScript settings to pass through.
   */
  public function __construct(
    private readonly SearchApiTypesenseBackend $backend,
    private readonly array $indexes,
    private readonly string $apiKey,
    private readonly string $blockUuid,
    private readonly int $hitsPerPage = 9,
    private readonly bool $debug = FALSE,
    private readonly ?string $currentLanguage = NULL,
    private readonly array $additionalSettings = [],
  ) {
  }

  /**
   * Gets the Typesense backend.
   *
   * @return \Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend
   *   The backend instance.
   */
  public function getBackend(): SearchApiTypesenseBackend {
    return $this->backend;
  }

  /**
   * Gets the Search API indexes.
   *
   * @return \Drupal\search_api\IndexInterface[]
   *   Array of indexes.
   */
  public function getIndexes(): array {
    return $this->indexes;
  }

  /**
   * Gets the API key.
   *
   * @return string
   *   The API key.
   */
  public function getApiKey(): string {
    return $this->apiKey;
  }

  /**
   * Gets the block UUID.
   *
   * @return string
   *   The block UUID.
   */
  public function getBlockUuid(): string {
    return $this->blockUuid;
  }

  /**
   * Gets the number of hits per page.
   *
   * @return int
   *   Number of hits per page.
   */
  public function getHitsPerPage(): int {
    return $this->hitsPerPage;
  }

  /**
   * Checks if debug mode is enabled.
   *
   * @return bool
   *   TRUE if debug mode is enabled.
   */
  public function isDebugEnabled(): bool {
    return $this->debug;
  }

  /**
   * Gets the current language code.
   *
   * @return string|null
   *   The current language code, or NULL if not set.
   */
  public function getCurrentLanguage(): ?string {
    return $this->currentLanguage;
  }

  /**
   * Gets additional JavaScript settings.
   *
   * @return array<string, mixed>
   *   Additional settings array.
   */
  public function getAdditionalSettings(): array {
    return $this->additionalSettings;
  }

}
