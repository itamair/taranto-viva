<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQL\DataProducer;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeFieldTypeInterface;

/**
 * Run field item resolution on graphql compose field type plugin.
 *
 * @DataProducer(
 *   id = "field_producer_plugin",
 *   name = @Translation("Field plugin resolver"),
 *   description = @Translation("Returns plugin field item resolution."),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("Field plugin item result"),
 *   ),
 *   consumes = {
 *     "plugin" = @ContextDefinition("any",
 *       label = @Translation("Field plugin instance"),
 *     ),
 *     "value" = @ContextDefinition("any",
 *       label = @Translation("Field values"),
 *     ),
 *   },
 * )
 */
class FieldProducerPlugin extends DataProducerPluginBase implements FieldProducerItemsInterface, FieldProducerItemInterface {

  /**
   * Resolve producer field items.
   *
   * @param \Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeFieldTypeInterface $plugin
   *   The plugin to process.
   * @param \Drupal\Core\Field\FieldItemListInterface|null $value
   *   The field values to process.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   The field context.
   *
   * @return mixed
   *   Results from resolution. Array for multiple.
   */
  public function resolve(GraphQLComposeFieldTypeInterface $plugin, ?FieldItemListInterface $value, FieldContext $context) {

    // How did you get here?
    if (!$value || !$value instanceof FieldItemListInterface) {
      return NULL;
    }

    // Process the field.
    return $this->resolveFieldItems($value, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function resolveFieldItems(FieldItemListInterface $field, FieldContext $context): array {
    $plugin = $this->getContextValue('plugin');

    if ($plugin instanceof FieldProducerItemsInterface) {
      return $plugin->resolveFieldItems($field, $context);
    }

    $results = [];
    foreach ($field as $item) {
      $results[] = $this->resolveFieldItem($item, $context);
    }

    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function resolveFieldItem(FieldItemInterface $item, FieldContext $context) {

    /** @var \Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeFieldTypeInterface $plugin */
    $plugin = $this->getContextValue('plugin');

    if ($plugin instanceof FieldProducerItemInterface) {
      return $plugin->resolveFieldItem($item, $context);
    }

    return $item->get($plugin->producerProperty ?? 'value')->getValue();
  }

}
