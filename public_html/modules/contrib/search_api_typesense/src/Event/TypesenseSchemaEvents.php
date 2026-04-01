<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Event;

/**
 * Defines events for the Typesense schema.
 */
final class TypesenseSchemaEvents {

  /**
   * Event fired when a Typesense schema is altered.
   *
   * This event allows modules to perform an action whenever a Typesense schema
   * is altered. The event listener method receives a
   * \Drupal\search_api_typesense\Event\TypesenseSchemaEvent instance.
   *
   * @Event
   *
   * @var string
   * @see \Drupal\search_api_typesense\Event\TypesenseSchemaEvent
   */
  const ALTER_SCHEMA = 'search_api_typesense.typesense_schema.alter';

}
