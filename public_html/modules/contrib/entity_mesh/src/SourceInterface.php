<?php

namespace Drupal\entity_mesh;

/**
 * Interface for source object.
 *
 * @package Drupal\entity_mesh
 */
interface SourceInterface {

  /**
   * Get the mesh type.
   *
   * @return string|null
   *   The mesh type.
   */
  public function getType(): ?string;

  /**
   * Set the mesh type.
   *
   * @param string $type
   *   The mesh type.
   */
  public function setType($type);

  /**
   * Get the source entity type.
   *
   * @return string|null
   *   The source entity type.
   */
  public function getSourceEntityType(): ?string;

  /**
   * Set the source entity type.
   *
   * @param string $source_entity_type
   *   The source entity type.
   */
  public function setSourceEntityType($source_entity_type);

  /**
   * Get the source entity ID.
   *
   * @return string|null
   *   The source entity ID.
   */
  public function getSourceEntityId(): ?string;

  /**
   * Set the source entity ID.
   *
   * @param string $source_entity_id
   *   The source entity ID.
   */
  public function setSourceEntityId(string $source_entity_id);

  /**
   * Get the source entity langcode.
   *
   * @return string|null
   *   The source entity langcode.
   */
  public function getSourceEntityLangcode(): ?string;

  /**
   * Set the source entity langcode.
   *
   * @param string $source_entity_langcode
   *   The source entity langcode.
   */
  public function setSourceEntityLangcode($source_entity_langcode);

  /**
   * Add a target.
   *
   * @param \Drupal\entity_mesh\TargetInterface $target
   *   The target.
   */
  public function addTarget(TargetInterface $target);

  /**
   * Get the targets.
   *
   * @return array
   *   The targets.
   */
  public function getTargets(): array;

  /**
   * Get the hash id.
   *
   * @return string|null
   *   The hash id or null.
   */
  public function getHashId(): ?string;

  /**
   * Set the hash id.
   */
  public function setHashId();

  /**
   * Get entity bundle.
   *
   * @return string|null
   *   The bundle entity or null.
   */
  public function getSourceEntityBundle(): ?string;

  /**
   * Set the entity bundle.
   */
  public function setSourceEntityBundle(string $entity_bundle);

  /**
   * Get the title.
   *
   * @return string|null
   *   The title.
   */
  public function getTitle(): ?string;

  /**
   * Set the title.
   *
   * @param string $title
   *   The title.
   */
  public function setTitle(string $title);

  /**
   * Convert the object to an array for storage.
   *
   * @return array
   *   The array representation of the object.
   */
  public function toArray(): array;

  /**
   * Static create method.
   *
   * @return \Drupal\entity_mesh\SourceInterface
   *   The source object.
   */
  public static function create(): SourceInterface;

}
