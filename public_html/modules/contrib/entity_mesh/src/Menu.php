<?php

namespace Drupal\entity_mesh;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\menu_link_content\MenuLinkContentInterface;

/**
 * Service description.
 */
class Menu extends Entity {

  /**
   * The menu link tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuLinkTree;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritDoc}
   */
  public function __construct(RepositoryInterface $entity_mesh_repository, EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager, ConfigFactoryInterface $config_factory, TrackerManagerInterface $tracker_manager) {
    parent::__construct($entity_mesh_repository, $entity_type_manager, $language_manager, $config_factory, $tracker_manager);
    $this->type = 'menu_link_content';
  }

  /**
   * {@inheritDoc}
   */
  public function createSourceWithTargetsFromEntity(EntityInterface $entity): ?SourceInterface {
    if (!$entity instanceof MenuLinkContentInterface || empty($entity->link)) {
      return NULL;
    }

    $linked_entity = $this->getMenuLinkedEntity($entity->link);
    if (!$linked_entity instanceof EntityInterface) {
      return NULL;
    }

    $source = $this->entityMeshRepository->instanceEmptySource();
    $target = $this->entityMeshRepository->instanceEmptyTarget();

    $source->setType($entity->getEntityTypeId());

    if ($this->ifProcessInternalTarget()) {
      $target->setLinkType('internal');
      $target->setEntityType($linked_entity->getEntityTypeId());
      $target->setEntityId((string) $linked_entity->id());
      $target->setEntityLangcode($linked_entity->language()->getId());
      $source->addTarget($target);
    }

    $parent_full_uuid = $entity->getParentId();
    if (empty($parent_full_uuid)) {
      return NULL;
    }

    // Format: "menu_link_content:1ad9292f-b800-4868-9f1a-47419e960f7c".
    $p = explode(':', $parent_full_uuid);
    if (empty($p[1])) {
      return NULL;
    }
    $parent_uuid = $p[1];
    $this->setSource($source, $parent_uuid);

    if (empty($source->getSourceEntityId())) {
      return NULL;
    }
    return $source;
  }

  /**
   * Set the source values from the menu_link parent.
   *
   * @param \Drupal\entity_mesh\SourceInterface $source
   *   The source object.
   * @param string $parent_uuid
   *   The parent uuid.
   */
  protected function setSource(SourceInterface $source, $parent_uuid) {
    $parent = $this->entityTypeManager->getStorage('menu_link_content')
      ->loadByProperties(['uuid' => $parent_uuid]);
    if (!empty($parent)) {
      $parent = reset($parent);
    }
    // @todo check $parent->getUrlObject()
    if ($parent instanceof EntityInterface && isset($parent->link)) {
      $linked_parent = $this->getMenuLinkedEntity($parent->link);
      if ($linked_parent instanceof EntityInterface) {
        $source->setSourceEntityType($linked_parent->getEntityTypeId());
        $source->setSourceEntityId((string) $linked_parent->id());
        $source->setSourceEntityLangcode($linked_parent->language()->getId());
      }
    }
  }

  /**
   * Get menu link entity.
   */
  public function getMenuLinkedEntity($link) {
    $uri = $link->uri ?: $link->uri;
    if (empty($uri)) {
      return;
    }

    $url = Url::fromUri($uri);

    if ($url->isExternal()) {
      return;
    }

    if (!$url->isRouted()) {
      return;
    }

    $route = $url->getRouteName();
    if ($route == '<front>') {
      return $this->frontPage();
    }

    $params = $url->getRouteParameters();
    if (!empty($params)) {
      $entity_type = key($params);
      if (!$this->entityTypeManager->hasDefinition($entity_type)) {
        return;
      }

      $storage = $this->entityTypeManager->getStorage($entity_type);
      $entity = $storage->load($params[$entity_type]);
      if ($entity instanceof EntityInterface) {
        return $entity;
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  public function createSourceFromEntity(EntityInterface $entity): ?SourceInterface {
    return $this->createBasicSourceFromEntity($entity);
  }

}
