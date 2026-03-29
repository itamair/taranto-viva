<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Annotation for GraphQL Compose field type plugins.
 *
 * @Annotation
 */
class GraphQLComposeFieldType extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The plugin description.
   *
   * @var string
   */
  public $description;

  /**
   * The Schema Definition Language name for the plugin. Eg id.
   *
   * Leave blank for automatic resolution.
   *
   * @var string
   */
  public $name_sdl;

  /**
   * The Schema Definition Language type for the plugin. Eg ID, String, Custom.
   *
   * @var string
   */
  public $type_sdl;

}
