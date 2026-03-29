<?php

namespace Drupal\entity_mesh;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Interface for target object.
 *
 * @package Drupal\entity_mesh
 */
interface TargetInterface {

  /**
   * Get the category.
   *
   * @return string|null
   *   The category.
   */
  public function getCategory(): ?string;

  /**
   * Set the category.
   *
   * @param string $category
   *   The category.
   */
  public function setCategory($category);

  /**
   * Get the Subcategory.
   *
   * @return string|null
   *   The subcategory.
   */
  public function getSubcategory(): ?string;

  /**
   * Set the Subcategory.
   *
   * @param string $sub_category
   *   The subcategory.
   */
  public function setSubcategory($sub_category);

  /**
   * Get the href.
   *
   * @return string|null
   *   The href or null.
   */
  public function getHref(): ?string;

  /**
   * Set the href.
   *
   * @param string|null $href
   *   The href.
   */
  public function setHref(?string $href);

  /**
   * Set the properties href, path, scheme and link type.
   *
   * @param string $href
   *   The href.
   */
  public function processHrefAndSetComponents(string $href);

  /**
   * Get the link type.
   *
   * @return string|null
   *   The link type or null.
   */
  public function getLinkType(): ?string;

  /**
   * Set the link type.
   *
   * @param string|null $link_type
   *   The link type.
   */
  public function setLinkType(?string $link_type);

  /**
   * Get the path.
   *
   * @return string|null
   *   The path or null.
   */
  public function getPath(): ?string;

  /**
   * Set the path.
   *
   * @param string|null $path
   *   The path.
   */
  public function setPath(?string $path);

  /**
   * Get the host.
   *
   * @return string|null
   *   The host or null.
   */
  public function getHost(): ?string;

  /**
   * Set the host.
   *
   * @param string|null $host
   *   The host.
   */
  public function setHost(?string $host);

  /**
   * Get the scheme.
   *
   * @return string|null
   *   The scheme or null.
   */
  public function getScheme(): ?string;

  /**
   * Set the scheme.
   *
   * @param string|null $scheme
   *   The scheme.
   */
  public function setScheme(?string $scheme);

  /**
   * Get the entity type.
   *
   * @return string|null
   *   The entity type or null.
   */
  public function getEntityType(): ?string;

  /**
   * Set the entity type.
   *
   * @param string|null $entity_type
   *   The entity type.
   */
  public function setEntityType(?string $entity_type);

  /**
   * Get the entity ID.
   *
   * @return string|null
   *   The entity ID or null.
   */
  public function getEntityId(): ?string;

  /**
   * Set the entity ID.
   *
   * @param string|null $entity_id
   *   The entity ID.
   */
  public function setEntityId(?string $entity_id);

  /**
   * Get the entity langcode.
   *
   * @return string|null
   *   The entity langcode or nul.
   */
  public function getEntityLangcode(): string;

  /**
   * Set the entity langcode.
   *
   * @param string|null $entity_langcode
   *   The entity langcode.
   */
  public function setEntityLangcode(?string $entity_langcode);

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
  public function getEntityBundle(): ?string;

  /**
   * Set the entity bundle.
   */
  public function setEntityBundle(string $entity_bundle);

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
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Request stack object.
   * @param bool $self_domain_internal
   *   Whether to use self domain internal.
   *
   * @return \Drupal\entity_mesh\TargetInterface
   *   The target object.
   */
  public static function create(RequestStack $request_stack, bool $self_domain_internal = TRUE): TargetInterface;

}
