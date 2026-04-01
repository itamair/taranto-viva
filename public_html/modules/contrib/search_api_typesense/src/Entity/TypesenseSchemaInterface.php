<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a typesense schema entity type.
 */
interface TypesenseSchemaInterface extends ConfigEntityInterface {

  /**
   * Returns the default sorting field.
   *
   * @return string
   *   The default sorting field.
   */
  public function getDefaultSortingField(): string;

  /**
   * Return the fields.
   *
   * @return array
   *   The fields.
   */
  public function getFields(): array;

  /**
   * Return the full schema.
   *
   * @return array
   *   The full schema.
   */
  public function getSchema(): array;

  /**
   * Check if the schema is equal to another schema.
   *
   * @param \Drupal\search_api_typesense\Entity\TypesenseSchemaInterface|null $other
   *   The schema to compare.
   *
   * @return bool
   *   TRUE if the schemas are equal, FALSE otherwise.
   */
  public function equals(?TypesenseSchemaInterface $other): bool;

  /**
   * Return string fields.
   *
   * @return array|null[]|string[]
   *   String fields.
   */
  public function getStringFields(): array;

  /**
   * Return true if the semantic search is enabled.
   *
   * @return bool
   *   True if the semantic search is enabled.
   */
  public function isEmbeddingEnabled(): bool;

}
