<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQL\DataProducer;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;

/**
 * Classes must implement a way to process a field item.
 */
interface FieldProducerItemInterface {

  /**
   * Resolve a field item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   Field value to process.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   Field context.
   *
   * @return mixed|void
   *   Result to pass to producer base.
   */
  public function resolveFieldItem(FieldItemInterface $item, FieldContext $context);

}
