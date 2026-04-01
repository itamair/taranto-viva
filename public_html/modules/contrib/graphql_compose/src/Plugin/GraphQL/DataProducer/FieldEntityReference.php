<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQL\DataProducer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\graphql\Plugin\GraphQL\DataProducer\Field\EntityReferenceTrait;
use GraphQL\Deferred;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Produce the referenced entities from a field.
 *
 * Note: If you find a nice way to put this into a buffer, that can be used
 * to resolve multiple entity types and revisions at once, please contrib!
 *
 * Deferred with referencedEntities covers a lot of use cases.
 *
 * @DataProducer(
 *   id = "field_entity_reference",
 *   name = @Translation("Field Entity Reference"),
 *   description = @Translation("Return entity references from a field."),
 *   produces = @ContextDefinition("mixed",
 *     label = @Translation("Referenced entities"),
 *   ),
 *   consumes = {
 *     "entity" = @ContextDefinition("entity",
 *       label = @Translation("Entity instance"),
 *     ),
 *     "field" = @ContextDefinition("string",
 *       label = @Translation("Field name"),
 *     ),
 *     "types" = @ContextDefinition("any",
 *      label = @Translation("Entity types allowed to load"),
 *      multiple = TRUE,
 *    ),
 *     "language" = @ContextDefinition("string",
 *       label = @Translation("Language to use"),
 *       required = FALSE,
 *     ),
 *   },
 * )
 */
class FieldEntityReference extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

  use EntityReferenceTrait;

  /**
   * Constructs a new EntityLoadByUuidOrId instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Finds the requested field on the entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The entity to resolve a field fields off.
   * @param string $field
   *   The field to resolve entities off.
   * @param array $types
   *   The union entity types allowed to load.
   * @param string|null $language
   *   The language to use.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   The field context.
   *
   * @return \GraphQL\Deferred|array
   *   The resolves entities from the field.
   */
  public function resolve(?EntityInterface $entity, string $field, array $types, ?string $language, FieldContext $context): Deferred|array {

    if (!$entity instanceof FieldableEntityInterface || !$entity->hasField($field)) {
      return [];
    }

    $field = $entity->get($field);
    if (!$field instanceof EntityReferenceFieldItemListInterface || !$field->access('view')) {
      return [];
    }

    // Resolve the entities.
    return new Deferred(function () use ($field, $types, $language, $context) {
      $entities = $field->referencedEntities();

      if ($language) {
        $entities = $this->getTranslated($entities, $language);
      }

      foreach ($entities as $entity) {
        $context->addCacheableDependency($entity);
      }

      $entities = $this->filterAccessible($entities, NULL, 'view', $context);

      // Add a list cache tags for each empty type.
      foreach ($types as $type) {
        $has_entity = array_filter(
          $entities,
          fn ($entity) => $entity->getEntityTypeId() === $type
        );

        if (!$has_entity) {
          $type = $this->entityTypeManager->getDefinition($type);
          $tags = $type->getListCacheTags();
          $context->addCacheTags($tags);
        }
      }

      return $entities;
    });
  }

}
