<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api_typesense\Event\TypesenseSchemaEvent;
use Drupal\search_api_typesense\Event\TypesenseSchemaEvents;

/**
 * Defines the typesense schema entity type.
 *
 * @ConfigEntityType(
 *   id = "typesense_schema",
 *   label = @Translation("Typesense schema"),
 *   label_collection = @Translation("Typesense schemas"),
 *   label_singular = @Translation("typesense schema"),
 *   label_plural = @Translation("typesense schemas"),
 *   label_count = @PluralTranslation(
 *     singular = "@count typesense schema",
 *     plural = "@count typesense schemas",
 *   ),
 *   handlers = {
 *     "form" = {
 *       "default" = "Drupal\search_api_typesense\Form\SchemaForm",
 *     },
 *   },
 *   config_prefix = "typesense_schema",
 *   admin_permission = "administer search_api",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   config_export = {
 *     "id",
 *     "default_sorting_field",
 *     "fields",
 *     "enable_embedding",
 *     "embedding_fields",
 *     "embedding_model",
 *     "chunk_prepend_fields",
 *     "chunk_size",
 *     "chunk_overlap_size",
 *   },
 * )
 */
final class TypesenseSchema extends ConfigEntityBase implements TypesenseSchemaInterface {

  /**
   * The schema ID.
   */
  protected string $id;

  /**
   * The default sorting field.
   */
  protected string $default_sorting_field;

  /**
   * The collection fields.
   */
  protected ?array $fields;

  /**
   * {@inheritdoc}
   */
  public function getDefaultSortingField(): string {
    return $this->default_sorting_field ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getFields(): array {
    return $this->fields ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSchema(): array {
    $schema = [
      'name' => $this->id,
      'fields' => [],
    ];

    // Typesense' default_sorting_field value is now optional. Don't add it
    // unless we have a value for it.
    if ($this->getDefaultSortingField() !== '-none-') {
      $schema['default_sorting_field'] = $this->getDefaultSortingField();
    }

    // Then add each field in turn.
    foreach ($this->getFields() as $name => $field) {
      // Start the current field's properties.
      $field_properties = [
        'name' => $name,
        'type' => $field['type'],
      ];

      // The field might be a facet.
      $field_properties['facet'] = $field['facet'] === TRUE;

      // The field might be optional.
      $field_properties['optional'] = $field['optional'] === TRUE;

      // The field might be indexed.
      $field_properties['index'] = $field['index'] === TRUE;

      // The field might be stored.
      $field_properties['store'] = $field['store'] === TRUE;

      // The field might be sortable.
      $field_properties['sort'] = $field['sort'] === TRUE;

      // The sort type might be set.
      $field_properties['sort_type'] = $field['sort_type'] ?? NULL;

      // The field might be infix-searched.
      $field_properties['infix'] = $field['infix'] === TRUE;

      if ($field['locale'] !== NULL) {
        // For configuring language specific tokenization.
        $field_properties['locale'] = $field['locale'];
      }

      // Values are stemmed before indexing.
      $field_properties['stem'] = $field['stem'] === TRUE;

      // The field might have a query weight.
      $field_properties['weight'] = $field['weight'];

      // Add the completed field to the list.
      $schema['fields'][] = $field_properties;
    }

    /** @var \Drupal\search_api_typesense\AiModels $ai_models */
    $ai_models = \Drupal::service(
      'search_api_typesense.ai_models',
    );

    if ($ai_models->isAiSupportAvailable()) {
      if ($this->isEmbeddingEnabled()) {
        $schema['fields'][] = [
          'name' => 'chunk',
          'type' => 'string',
          'facet' => FALSE,
          'optional' => FALSE,
          'index' => TRUE,
          'store' => TRUE,
          'sort' => FALSE,
          'infix' => FALSE,
          'stem' => FALSE,
          'weight' => 1,
        ];

        $schema['fields'][] = [
          'name' => 'document_id',
          'type' => 'string',
          'facet' => TRUE,
          'optional' => FALSE,
          'index' => TRUE,
          'store' => TRUE,
          'sort' => FALSE,
          'infix' => FALSE,
          'stem' => FALSE,
          'weight' => 1,
        ];

        $schema['fields'][] = [
          'name' => 'embedding',
          'type' => 'float[]',
          'embed' => [
            'from' => ['chunk'],
            'model_config' => $ai_models->getEmbeddingModelConfig(
              $this->get('embedding_model'),
            ),
          ],
        ];
      }
    }

    $typesense_schema_event = new TypesenseSchemaEvent($this, $schema);
    \Drupal::service('event_dispatcher')
      ->dispatch(
        $typesense_schema_event,
        TypesenseSchemaEvents::ALTER_SCHEMA,
      );

    return $typesense_schema_event->getGeneratedSchema();
  }

  /**
   * {@inheritdoc}
   */
  public function equals(?TypesenseSchemaInterface $other): bool {
    if ($other === NULL) {
      return FALSE;
    }

    return \json_encode($this->getSchema()) === \json_encode(
        $other->getSchema(),
      );
  }

  /**
   * {@inheritdoc}
   */
  public function getStringFields(): array {
    /** @var \Drupal\search_api\Entity\Index|null $index */
    $index = \Drupal::service('entity_type.manager')
      ->getStorage('search_api_index')
      ->load($this->id);

    return \array_map(
      callback: static function (FieldInterface $field) {
        return $field->getLabel();
      },
      array   : \array_filter(
        array   : $index?->getFields() ?? [],
        callback: static function (FieldInterface $field) {
          return \in_array(
            needle  : $field->getType(),
            haystack: ['typesense_string', 'typesense_string[]'],
            strict  : TRUE,
          );
        },
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isEmbeddingEnabled(): bool {
    return (bool) $this->get('enable_embedding') === TRUE;
  }

}
