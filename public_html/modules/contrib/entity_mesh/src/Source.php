<?php

namespace Drupal\entity_mesh;

/**
 * Class Source to instance source target objects.
 *
 * The source is the main entity that has mesh targets.
 *
 * @package Drupal\entity_mesh
 */
class Source implements SourceInterface {

  use HelperTrait;

  /**
   * Hash ID.
   *
   * @var string|null
   */
  protected $hashId;

  /**
   * The mesh type.
   *
   * @var string|null
   */
  protected $type;

  /**
   * Source Entity Type.
   *
   * @var string|null
   */
  protected $sourceEntityType;

  /**
   * Source Entity ID.
   *
   * @var string|null
   */
  protected $sourceEntityId;

  /**
   * Entity Bundle.
   *
   * @var string|null
   */
  protected $sourceEntityBundle;

  /**
   * Langcode.
   *
   * @var string|null
   */
  protected $sourceEntityLangcode;

  /**
   * Title.
   *
   * @var string|null
   */
  protected $title;

  /**
   * The mesh targets.
   *
   * @var array<TargetInterface>
   */
  protected $targets = [];

  /**
   * {@inheritdoc}
   */
  public static function create(): Source {
    return new self();
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): ?string {
    return $this->type ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setType($type) {
    $this->type = $type;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceEntityType(): ?string {
    return $this->sourceEntityType ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setSourceEntityType($source_entity_type) {
    $this->sourceEntityType = $source_entity_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceEntityId(): ?string {
    return $this->sourceEntityId ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setSourceEntityId(string $source_entity_id) {
    $this->sourceEntityId = $source_entity_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceEntityLangcode(): ?string {
    return $this->sourceEntityLangcode ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setSourceEntityLangcode($source_entity_langcode) {
    $this->sourceEntityLangcode = $source_entity_langcode;
  }

  /**
   * {@inheritdoc}
   */
  public function addTarget($target) {
    $this->targets[] = $target;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargets(): array {
    return $this->targets;
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
  public function setHashId() {
    $string = '';
    if ($this->getSourceEntityType() && $this->getSourceEntityId()) {
      $string = $this->getSourceEntityType() . $this->getSourceEntityId();
    }
    $this->hashId = $this->generateHash($string);
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceEntityBundle(): ?string {
    return $this->sourceEntityBundle ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setSourceEntityBundle(string $entity_bundle) {
    $this->sourceEntityBundle = $entity_bundle;
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
  public function toArray(): array {
    return [
      'type' => $this->getType(),
      'source_entity_type' => $this->getSourceEntityType(),
      'source_entity_id' => $this->getSourceEntityId(),
      'source_entity_langcode' => $this->getSourceEntityLangcode(),
      'source_hash_id' => $this->getHashId(),
      'source_entity_bundle' => $this->getSourceEntityBundle(),
      'source_title' => $this->getTitle(),
    ];
  }

}
