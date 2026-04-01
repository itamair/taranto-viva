<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQLCompose\FieldType;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerItemInterface;
use Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerTrait;
use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeFieldTypeBase;
use function Symfony\Component\String\u;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeFieldType(
 *   id = "double_field",
 * )
 */
class DoubleFieldItem extends GraphQLComposeFieldTypeBase implements FieldProducerItemInterface {

  use FieldProducerTrait;

  /**
   * {@inheritdoc}
   */
  public function resolveFieldItem(FieldItemInterface $item, FieldContext $context) {
    return [
      'first' => $this->getSubField('first', $item, $context) ?: NULL,
      'second' => $this->getSubField('second', $item, $context) ?: NULL,
    ];
  }

  /**
   * Get the subfield value for a subfield.
   *
   * @param string $delta
   *   The delta of the subfield. (first, second)
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The field item.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   The field context.
   *
   * @return string
   *   The value of the subfield.
   */
  protected function getSubField(string $delta, FieldItemInterface $item, FieldContext $context) {
    $value = $item->{$delta};

    // Attempt to load the plugin for the field type.
    $plugin = $this->getSubfieldPlugin($delta);
    if (!$plugin) {
      return $value;
    }

    // Check if it has a resolver we can hijack.
    $class = new \ReflectionClass($plugin['class']);
    if (!$class->implementsInterface(FieldProducerItemInterface::class)) {
      return $value;
    }

    // Create an instance of the graphql plugin.
    $instance = $this->gqlFieldTypeManager->createInstance($plugin['id'], []);

    // Clone the current item into a new object for safety.
    $clone = clone $item;

    // Generically set the value. Relies on magic method __set().
    $clone->value = $value;

    // Snowflake items.
    if ($instance instanceof LinkItem) {
      $clone->uri = $value;
    }
    elseif ($instance instanceof TextItem) {
      $clone->processed = check_markup($value);
    }

    // Call the plugin resolver on the sub field.
    return $instance->resolveFieldItem($clone, $context);
  }

  /**
   * {@inheritdoc}
   *
   * Override the type resolution for this field item.
   */
  public function getTypeSdl(): string {
    $type = u('Double');

    foreach (['first', 'second'] as $subfield) {
      $sub = $this->getSubfieldTypeSdl($subfield);
      $type = $type->append(u($sub)->title()->toString());
    }

    return $type->toString();
  }

  /**
   * Get the subfield type for a subfield.
   *
   * @param string $subfield
   *   The subfield to get the type for. Eg first, second.
   *
   * @return string
   *   The SDL type of the subfield.
   */
  public function getSubfieldTypeSdl(string $subfield): string {
    $plugin = $this->getSubfieldPlugin($subfield);
    return $plugin['type_sdl'] ?? 'String';
  }

  /**
   * Get the data definition type from DoubleField.
   *
   * @param string $subfield
   *   The subfield to get the plugin for. Eg first, second.
   *
   * @return array|null
   *   The plugin definition or NULL if not found.
   */
  protected function getSubfieldPlugin(string $subfield): ?array {
    $storage = $this->getFieldDefinition()->getFieldStorageDefinition();
    $settings = $storage->getSettings();

    // Fortunately the types double_field supports isn't too large.
    // @see DoubleField::isListAllowed()
    $type = $settings['storage'][$subfield]['type'];

    // Coerce them back into our schema supported type.
    switch ($type) {
      case 'numeric':
        $type = 'decimal';
        break;

      case 'datetime_iso8601':
        $type = 'datetime';
        break;

      case 'uri':
        $type = 'link';
        break;
    }

    return $this->gqlFieldTypeManager->getDefinition($type, FALSE);
  }

}
