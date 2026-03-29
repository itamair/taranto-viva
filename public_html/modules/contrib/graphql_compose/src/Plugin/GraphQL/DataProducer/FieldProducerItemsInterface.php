<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQL\DataProducer;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;

/**
 * Classes must implement a way to process a field items.
 */
interface FieldProducerItemsInterface {

  /**
   * Resolve a field's items.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   Field to process.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   Field context.
   *
   * @return array
   *   Result to pass to producer base.
   */
  public function resolveFieldItems(FieldItemListInterface $field, FieldContext $context): array;

}
