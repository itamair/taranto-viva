<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\search_api\IndexInterface;

/**
 * Event fired when a Typesense collection name is generated.
 */
class TypesenseCollectionNameEvent extends Event {

  public function __construct(
    private readonly IndexInterface $index,
    private string $collectionName,
  ) {}

  /**
   * Returns the Search API index.
   *
   * @return \Drupal\search_api\IndexInterface
   *   The Search API index.
   */
  public function getIndex(): IndexInterface {
    return $this->index;
  }

  /**
   * Returns the collection name.
   *
   * @return string
   *   The collection name.
   */
  public function getCollectionName(): string {
    return $this->collectionName;
  }

  /**
   * Sets the collection name.
   *
   * @param string $collection_name
   *   The collection name to set.
   */
  public function setCollectionName(string $collection_name): void {
    $this->collectionName = $collection_name;
  }

}
