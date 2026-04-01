<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_eck\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use function Symfony\Component\String\u;

/**
 * Derivatives of entity.
 */
class EckEntityTypeDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * Constructs a new EckEntityTypeDeriver.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * Return ECK entity types.
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    // Short list of allowed base fields to add by default.
    $eck_base_fields = [
      'created',
      'changed',
      'title',
      'status',
      'path',
      'langcode',
    ];

    /** @var \Drupal\eck\EckEntityTypeInterface[] $entity_types */
    $entity_types = $this->entityTypeManager
      ->getStorage('eck_entity_type')
      ->loadMultiple();

    foreach (array_keys($entity_types) as $entity_type_id) {

      // Create a dynamic prefix off entity type.
      $prefix = u($entity_type_id)
        ->camel()
        ->title()
        ->toString();

      // Get all base fields from the eck type.
      $base_definitions = $this->entityFieldManager->getBaseFieldDefinitions($entity_type_id);
      $base_fields = array_intersect($eck_base_fields, array_keys($base_definitions));
      $base_fields = array_map(fn () => [], array_flip($base_fields));

      // Follow the pattern.
      if (isset($base_definitions['title'])) {
        $base_fields['title'] = [
          'field_type' => 'entity_label',
        ];
      }

      $this->derivatives[$entity_type_id] = [
        'id' => $entity_type_id,
        'prefix' => $prefix,
        'base_fields' => $base_fields,
      ] + $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
