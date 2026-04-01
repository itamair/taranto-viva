<?php

namespace Drupal\Tests\entity_mesh\Kernel\Traits;

/**
 * Provides common methods for Entity Mesh tests.
 */
trait EntityMeshTestTrait {

  /**
   * Fetches records from the 'entity_mesh' table.
   */
  protected function fetchEntityMeshRecords() {
    $connection = $this->container->get('database');
    $query = $connection->select('entity_mesh', 'em')
      ->fields('em', [
        'id',
        'type',
        'category',
        'subcategory',
        'source_entity_id',
        'source_entity_type',
        'source_entity_bundle',
        'source_entity_langcode',
        'source_title',
        'target_href',
        'target_path',
        'target_scheme',
        'target_link_type',
        'target_entity_type',
        'target_entity_bundle',
        'target_entity_id',
        'target_title',
        'target_entity_langcode',
      ]);
    $result = $query->execute();
    return $result ? $result->fetchAllAssoc('id', \PDO::FETCH_ASSOC) : [];
  }

}
