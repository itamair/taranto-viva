<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_layout_builder\Plugin\GraphQL\DataProducer;

use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\layout_builder\Plugin\Block\FieldBlock;

/**
 * Layout Builder section component block loader.
 *
 * @DataProducer(
 *   id = "field_block_entity_load",
 *   name = @Translation("Layout Builder field block entity"),
 *   description = @Translation("Get layout builder component field block entity."),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("Entity"),
 *   ),
 *   consumes = {
 *     "block_instance" = @ContextDefinition("any",
 *       label = @Translation("Block"),
 *     ),
 *   },
 * )
 */
class FieldBlockEntityLoad extends DataProducerPluginBase {

  /**
   * Resolves an entity of a Field Block.
   *
   * @param \Drupal\layout_builder\Plugin\Block\FieldBlock $block_instance
   *   The field block to load the entity off.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   Cacheability context for this request.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface|null
   *   Entity attached to block
   */
  public function resolve(FieldBlock $block_instance, FieldContext $context) {
    $method = new \ReflectionMethod($block_instance::class, 'getEntity');
    $method->setAccessible(TRUE);

    $entity = $method->invoke($block_instance);
    if (!$entity) {
      return NULL;
    }

    $access = $entity->access('view', NULL, TRUE);

    $context->addCacheableDependency($entity);
    $context->addCacheableDependency($access);

    if (!$access->isAllowed()) {
      return NULL;
    }

    return $entity;
  }

}
