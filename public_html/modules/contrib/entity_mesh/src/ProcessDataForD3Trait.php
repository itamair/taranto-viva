<?php

namespace Drupal\entity_mesh;

use Drupal\Core\Url;

/**
 * Trait for processing data to be consumed by D3.
 *
 * @package Drupal\entity_mesh
 */
trait ProcessDataForD3Trait {

  /**
   * The field prefix.
   *
   * @var string
   */
  protected $fieldPrefix = '';

  /**
   * Process data for D3.
   *
   * @param array $data
   *   The data to process.
   * @param string $field_prefix
   *   The field prefix.
   *
   * @return array
   *   The processed data.
   */
  public function processData(array $data, $field_prefix = ''): array {
    $this->fieldPrefix = $field_prefix;

    $nodes = [];
    $links = [];
    $types = [];

    foreach ($data as $row) {
      if ($this->ifExcludedRow($row)) {
        continue;
      }
      $this->processRaw($nodes, $links, $types, $row);
    }

    asort($nodes);
    asort($types);
    asort($links);

    return [
      'nodes' => array_values($nodes),
      'links' => array_values($links),
      'types' => array_values($types),
    ];
  }

  /**
   * Set the values in Nodes, Links and Types arrays.
   *
   * @param array $nodes
   *   The nodes array.
   * @param array $links
   *   The links array.
   * @param array $types
   *   The types array.
   * @param object $row
   *   The row object.
   */
  protected function processRaw(&$nodes, &$links, &$types, $row) {
    $type = NULL;

    $source_id = NULL;
    $source_type = NULL;
    $source_label = NULL;

    $target_id = NULL;
    $target_type = NULL;
    $target_label = NULL;

    $type = $this->getValueFromObject($row, "type");
    $this->setSourceData($source_id, $source_type, $source_label, $row);
    $this->setTargetData($target_id, $target_type, $target_label, $row);

    $source_entity_type = $this->getValueFromObject($row, "source_entity_type");
    $source_entity_id = $this->getValueFromObject($row, "source_entity_id");
    $source_entity_langcode = $this->getValueFromObject($row, "source_entity_langcode");
    $route_name = "entity.{$source_entity_type}.canonical";
    $url = Url::fromRoute($route_name, [$source_entity_type => $source_entity_id], ['language' => \Drupal::languageManager()->getLanguage($source_entity_langcode)])->toString();

    // Registering source items:
    if (!isset($nodes[$source_id])) {
      $nodes[$source_id] = [
        'id' => $source_id,
        'type' => $source_type,
        'label' => $source_label,
        'url' => $url,
        'total' => 1,
      ];
    }
    else {
      $nodes[$source_id]['total']++;
    }

    // Registering target items:
    if (!isset($nodes[$target_id])) {
      $nodes[$target_id] = [
        'id' => $target_id,
        'type' => $target_type,
        'label' => $target_label,
        'url' => $this->getValueFromObject($row, "target_href"),
        'total' => 1,
      ];
    }
    else {
      $nodes[$target_id]['total']++;
    }

    // Register the relation.
    $links[] = [
      "source" => $source_id,
      "target" => $target_id,
      'type' => $type,
    ];

    $types[$source_type] = $source_type;
    $types[$target_type] = $target_type;
  }

  /**
   * Process the raw data for source.
   */
  protected function setSourceData(&$source_id, &$source_type, &$source_label, $row) {
    $source_id = $this->getValueFromObject($row, "source_hash_id");
    $source_type = $this->getValueFromObject($row, "source_entity_type") . '-' . $this->getValueFromObject($row, "source_entity_bundle");
    $source_label = $this->getValueFromObject($row, "source_title");
  }

  /**
   * Process the raw data for target.
   */
  protected function setTargetData(&$target_id, &$target_type, &$target_label, $row) {
    $target_id = $this->getValueFromObject($row, "target_hash_id");

    // Target data.
    if ($this->getValueFromObject($row, "target_link_type") === "external") {
      $target_type = 'external';
      $target_label = $this->getValueFromObject($row, "target_title");
    }
    else {
      $target_type = $this->getValueFromObject($row, "target_entity_type");
      $target_type .= !empty($this->getValueFromObject($row, "target_entity_bundle")) ? '-' . $this->getValueFromObject($row, "target_entity_bundle") : '';
      $target_label = $this->getValueFromObject($row, "target_title");
    }
  }

  /**
   * Get the value from an object.
   *
   * @param object $row
   *   The object.
   * @param string $field
   *   The field.
   *
   * @return mixed
   *   The value.
   */
  protected function getValueFromObject($row, $field) {
    if ($this->fieldPrefix !== '') {
      $field = $this->fieldPrefix . $field;
    }
    if (!isset($row->{$field})) {
      return NULL;
    }
    return $row->{$field};
  }

  /**
   * Check if the row is excluded.
   *
   * @param object $row
   *   The row.
   *
   * @return bool
   *   TRUE if the row is excluded, FALSE otherwise.
   */
  protected function ifExcludedRow($row): bool {
    $target_link_type = $this->getValueFromObject($row, "target_link_type");
    $target_entity_type = $this->getValueFromObject($row, "target_entity_type");

    $source_id = $this->getValueFromObject($row, "source_hash_id");
    $target_id = $this->getValueFromObject($row, "target_hash_id");

    if ($source_id === $target_id) {
      return TRUE;
    }

    if ($target_link_type === 'internal' && empty($target_entity_type)) {
      return TRUE;
    }

    return FALSE;
  }

}
