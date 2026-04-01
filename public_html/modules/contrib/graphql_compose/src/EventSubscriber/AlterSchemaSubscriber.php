<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\EventSubscriber;

use Drupal\graphql\Event\AlterSchemaDataEvent;
use Drupal\graphql\Event\AlterSchemaExtensionDataEvent;
use Drupal\graphql_compose\Plugin\GraphQLComposeSchemaTypeManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Extend the schema with GraphQL Compose.
 */
class AlterSchemaSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new ConfigEventsSubscriber object.
   *
   * @param \Drupal\graphql_compose\Plugin\GraphQLComposeSchemaTypeManager $gqlSchemaTypeManager
   *   The GraphQL schema type manager.
   */
  public function __construct(
    protected GraphQLComposeSchemaTypeManager $gqlSchemaTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      AlterSchemaExtensionDataEvent::EVENT_NAME => ['alterExtensions'],
      AlterSchemaDataEvent::EVENT_NAME => ['alterSchema'],
    ];
  }

  /**
   * Alter the schema data.
   *
   * @param \Drupal\graphql\Event\AlterSchemaExtensionDataEvent $event
   *   The alter schema data event.
   */
  public function alterExtensions(AlterSchemaExtensionDataEvent $event) {
    $schema = $event->getSchemaExtensionData();

    $schema[] = $this->gqlSchemaTypeManager->printExtensions();

    $event->setSchemaExtensionData($schema);
  }

  /**
   * Alter the schema data.
   *
   * @param \Drupal\graphql\Event\AlterSchemaDataEvent $event
   *   The alter schema data event.
   */
  public function alterSchema(AlterSchemaDataEvent $event) {
    $schema = $event->getSchemaData();

    $schema[] = $this->gqlSchemaTypeManager->printTypes();

    $event->setSchemaData($schema);
  }

}
