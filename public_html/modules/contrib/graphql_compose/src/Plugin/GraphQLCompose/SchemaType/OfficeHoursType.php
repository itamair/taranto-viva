<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQLCompose\SchemaType;

use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeSchemaTypeBase;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeSchemaType(
 *   id = "OfficeHours",
 * )
 */
class OfficeHoursType extends GraphQLComposeSchemaTypeBase {

  /**
   * {@inheritdoc}
   */
  public function getTypes(): array {
    $types = [];

    if (!$this->moduleHandler->moduleExists('office_hours')) {
      return $types;
    }

    $types[] = new ObjectType([
      'name' => $this->getPluginId(),
      'description' => (string) $this->t('This field stores office hours information.'),
      'fields' => fn() => [
        'day' => [
          'type' => Type::nonNull(Type::int()),
          'description' => (string) $this->t('Number of the day of the week. 0=Sunday, 1=Monday, etc.'),
        ],
        'dayDelta' => [
          'type' => Type::nonNull(Type::int()),
          'description' => (string) $this->t('The delta for the current day. Used if there is multiple office hours for the same day.'),
        ],
        'allDay' => [
          'type' => Type::nonNull(Type::boolean()),
          'description' => (string) $this->t('True if the office hours are all day. Start and End will be ignored.'),
        ],
        'startHours' => [
          'type' => Type::int(),
          'description' => (string) $this->t('Start (opening) time in military time format (ex: 1300 for 1:00 or 13:00).'),
        ],
        'endHours' => [
          'type' => Type::int(),
          'description' => (string) $this->t('End (closing) time in military time format (ex: 1300 for 1:00 or 13:00).'),
        ],
        'comment' => [
          'type' => Type::string(),
          'description' => (string) $this->t('Comment for office hours day.'),
        ],
      ],
    ]);

    return $types;
  }

}
