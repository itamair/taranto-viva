<?php

namespace Drupal\Tests\entity_mesh\Kernel;

use Drupal\Tests\entity_mesh\Kernel\Traits\EntityMeshTestTrait;

/**
 * Entity mesh kernel base.
 */
abstract class EntityMeshTestBase extends EntityMeshTestBasic {

  use EntityMeshTestTrait;

  /**
   * Provider defaults for test cases.
   *
   * @var array<string>
   */
  protected static array $providerDefaults = [
    'source_entity_id'                 => NULL,
    'source_entity_langcode'           => NULL,
    'target_href'                      => NULL,
    'expected_target_link_type'        => NULL,
    'expected_target_subcategory'      => NULL,
    'expected_target_title'            => NULL,
    'expected_target_scheme'           => NULL,
    'expected_target_host'             => NULL,
    'expected_target_entity_type'      => NULL,
    'expected_target_entity_bundle'    => NULL,
    'expected_target_entity_id'        => NULL,
    'expected_target_entity_langcode'  => NULL,
    'expected_source_entity_type'      => NULL,
    'expected_source_entity_bundle'    => NULL,
    'expected_source_entity_langcode'  => NULL,
    'expected_source_title'            => NULL,
    'source_should_exist'              => TRUE,
  ];

  /**
   * File Media Type.
   *
   * @var \Drupal\media\MediaTypeInterface
   */
  protected $fileMediaType;

  /**
   * Creates a node with given content.
   */
  abstract protected function createExampleNodes();

  /**
   * Provides test cases for different types of links.
   */
  abstract public static function linkCasesProvider();

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
        'target_host',
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

  /**
   * Tests different types of links.
   *
   * @dataProvider linkCasesProvider
   */
  public function testLinks(
    $source_entity_id,
    $source_entity_langcode,
    $target_href,
    $expected_target_link_type,
    $expected_target_subcategory = NULL,
    $expected_target_title = NULL,
    $expected_target_scheme = NULL,
    $expected_target_host = NULL,
    $expected_target_entity_type = NULL,
    $expected_target_entity_bundle = NULL,
    $expected_target_entity_id = NULL,
    $expected_target_entity_langcode = NULL,
    $expected_source_entity_type = NULL,
    $expected_source_entity_bundle = NULL,
    $expected_source_entity_langcode = NULL,
    $expected_source_title = NULL,
    $source_should_exist = TRUE,
  ) {

    // Fetch records from entity_mesh table for assertions.
    $records = $this->fetchEntityMeshRecords();

    // Filter records based on node ID and link type.
    $filtered = array_filter($records, function ($record) use ($source_entity_id, $target_href, $source_entity_langcode) {
      $matches = $record['source_entity_id'] == $source_entity_id &&
        ($record['target_href'] === $target_href || ($record['target_entity_type'] === 'media' && str_contains($record['target_href'], $target_href)));

      if ($source_entity_langcode !== NULL) {
        $matches = $matches && $record['source_entity_langcode'] === $source_entity_langcode;
      }

      return $matches;
    });

    // Extract the record by matching 'target_href' with $expected_href.
    $record = reset($filtered);

    if ($source_should_exist) {
      $this->assertNotEmpty($record, "No record found with source_entity_id: $source_entity_id and target_href: $target_href");
    }
    else {
      $this->assertEmpty($record, "Record found with source_entity_id: $source_entity_id and target_href: $target_href");
      return;
    }

    $checks = [
      'target_link_type'       => $expected_target_link_type,
      'subcategory'            => $expected_target_subcategory,
      'target_title'           => $expected_target_title,
      'target_scheme'          => $expected_target_scheme,
      'target_host'            => $expected_target_host,
      'target_entity_type'     => $expected_target_entity_type,
      'target_entity_bundle'   => $expected_target_entity_bundle,
      'target_entity_id'       => $expected_target_entity_id,
      'target_entity_langcode' => $expected_target_entity_langcode,
      'source_entity_type'     => $expected_source_entity_type,
      'source_entity_bundle'   => $expected_source_entity_bundle,
      'source_entity_langcode' => $expected_source_entity_langcode,
      'source_title'           => $expected_source_title,
    ];

    foreach ($checks as $key => $expectedValue) {
      if ($expectedValue !== NULL) {
        $this->assertEquals($expectedValue, $record[$key]);
      }
    }
  }

}
