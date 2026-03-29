<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_menus\Wrapper;

use Drupal\graphql_compose\Wrapper\EntityTypeWrapper;

/**
 * Override EntityTypeWrapper for MenuLinkContent.
 */
class MenuLinkContentWrapper extends EntityTypeWrapper {

  /**
   * Enable the menu link content type if the menu is enabled.
   *
   * @return bool
   *   True if the bundle is enabled.
   */
  public function isEnabled(): bool {
    $settings = $this->configFactory->get('graphql_compose.settings');
    return $settings->get('entity_config.menu.' . $this->entity->id() . '.enabled') ?: FALSE;
  }

}
