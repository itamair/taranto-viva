<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Event;

/**
 * Defines events for interacting with Typesense collections.
 */
final class TypesenseCollectionEvents {

  /**
   * Name of the event fired when a collection name is generated.
   *
   * This event allows modules to alter the name of a Typesense collection. The
   * event listener method receives a
   * \Drupal\search_api_typesense\Event\TypesenseCollectionEvent instance.
   *
   * @Event
   * @var string
   *
   * @see \Drupal\search_api_typesense\Event\TypesenseCollectionEvent
   * @see \Drupal\search_api_typesense\Plugin\search_api\backend\SearchApiTypesenseBackend::getCollectionName()
   */
  const ALTER_NAME = 'search_api_typesense.typesense_collection.alter_name';

}
