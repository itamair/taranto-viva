<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Annotation for GraphQL Compose entity type plugins.
 *
 * @Annotation
 */
class GraphQLComposeEntityType extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * Use this as a type name instead of the id.
   *
   * @var string
   */
  public $type_sdl;

  /**
   * The plugin SDL interfaces.
   *
   * @var array
   */
  public $interfaces;

  /**
   * The plugin SDL prefix.
   *
   * @var null|string
   */
  public $prefix;

  /**
   * Base fields to allow on the entity.
   *
   * @var array
   */
  public $base_fields;

}
