<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_menus\Plugin\GraphQL\DataProducer;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\graphql\GraphQL\Buffers\EntityUuidBuffer;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\menu_link_content\Plugin\Menu\MenuLinkContent;
use GraphQL\Deferred;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns the menu link content entity of a menu link.
 *
 * @DataProducer(
 *   id = "menu_link_entity",
 *   name = @Translation("Menu link content entity"),
 *   description = @Translation("Returns the menu link content of a menu link."),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("Menu Link Content entity"),
 *   ),
 *   consumes = {
 *     "link" = @ContextDefinition("any",
 *       label = @Translation("Menu link tree element")
 *     ),
 *   },
 * )
 */
class MenuLinkEntity extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Create the menu link translated URL resolver.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository service.
   * @param \Drupal\graphql\GraphQL\Buffers\EntityUuidBuffer $entityBuffer
   *   The entity buffer service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityRepositoryInterface $entityRepository,
    protected EntityUuidBuffer $entityBuffer,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.repository'),
      $container->get('graphql.buffer.entity_uuid'),
    );
  }

  /**
   * Resolve the language of the menu item.
   *
   * @param \Drupal\Core\Menu\MenuLinkInterface $link
   *   The menu link plugin to resolve the entity.
   *
   * @return \GraphQL\Deferred|null
   *   The menu link content entity or null.
   */
  public function resolve(MenuLinkInterface $link): ?Deferred {

    if (!$link instanceof MenuLinkContent) {
      return NULL;
    }

    $derivative_id = $link->getDerivativeId();

    if (!Uuid::isValid($derivative_id)) {
      return NULL;
    }

    $resolver = $this->entityBuffer->add('menu_link_content', $derivative_id);

    return new Deferred(function () use ($resolver) {
      if ($entity = $resolver()) {
        return $this->entityRepository->getTranslationFromContext($entity);
      }

      return NULL;
    });
  }

}
