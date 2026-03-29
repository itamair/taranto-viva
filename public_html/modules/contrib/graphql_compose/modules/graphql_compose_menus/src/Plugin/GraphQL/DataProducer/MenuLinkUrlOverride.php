<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_menus\Plugin\GraphQL\DataProducer;

use Drupal\Core\Url;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;

/**
 * Returns the translated URL object of a menu link.
 *
 * @DataProducer(
 *   id = "menu_link_url_override",
 *   name = @Translation("Menu link translated url"),
 *   description = @Translation("Returns the translated URL of a menu link."),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("URL"),
 *   ),
 *   consumes = {
 *     "entity" = @ContextDefinition("any",
 *       label = @Translation("Menu link content entity"),
 *     ),
 *   },
 * )
 */
class MenuLinkUrlOverride extends DataProducerPluginBase {

  /**
   * Resolve the translated menu link url.
   *
   * @param \Drupal\menu_link_content\Entity\MenuLinkContent $entity
   *   The menu link content entity to resolve the url off of.
   *
   * @return \Drupal\Core\Url
   *   The Url.
   */
  public function resolve(MenuLinkContent $entity): Url {
    if ($entity->hasField('link_override')) {
      /** @var \Drupal\link\LinkItemInterface|null $link_override */
      $link_override = $entity->get('link_override')->first();

      if ($link_override && !$link_override->isEmpty()) {
        return $link_override->getUrl();
      }
    }

    return $entity->getUrlObject();
  }

}
