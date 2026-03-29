<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQLCompose\FieldType;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeFieldType(
 *   id = "text_with_summary",
 *   type_sdl = "TextSummary",
 * )
 */
class TextWithSummaryItem extends TextItem {

  /**
   * {@inheritdoc}
   */
  public function resolveFieldItem(FieldItemInterface $item, FieldContext $context) {

    $result = parent::resolveFieldItem($item, $context);
    $result['summary'] = $item->summary;

    return $result;
  }

}
