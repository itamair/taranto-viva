<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_menus\Plugin\GraphQLCompose\EntityType;

use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeEntityTypeBase;
use Drupal\graphql_compose\Wrapper\EntityTypeWrapper;

/**
 * {@inheritdoc}
 *
 * Re-wrap the bundles in a utility wrap to change what is enabled.
 * This is intended to be used with the menu_item_extras module.
 *
 * @GraphQLComposeEntityType(
 *   id = "menu_link_content",
 *   prefix = "MenuLinkContent",
 *   base_fields = {},
 * )
 */
class MenuLinkContent extends GraphQLComposeEntityTypeBase {

  /**
   * {@inheritdoc}
   */
  public function wrapBundle($bundle): EntityTypeWrapper {
    return \Drupal::service('graphql_compose_menus.entity_type_wrapper')
      ->setEntityTypePlugin($this)
      ->setEntity($bundle);
  }

}
