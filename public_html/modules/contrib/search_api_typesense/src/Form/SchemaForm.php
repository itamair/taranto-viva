<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api_typesense\AiModels;
use Drupal\search_api_typesense\Api\SearchApiTypesenseException;
use Drupal\search_api_typesense\Entity\TypesenseSchemaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to manage Typesense the schema entity.
 */
final class SchemaForm extends EntityForm {

  /**
   * SchemaForm constructor.
   *
   * @param \Drupal\search_api_typesense\AiModels $aiModels
   *   The AI models.
   */
  public function __construct(
    private readonly AiModels $aiModels,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('search_api_typesense.ai_models'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFromRouteMatch(
    RouteMatchInterface $route_match,
    $entity_type_id,
  ): EntityInterface {
    if ($entity_type_id !== 'typesense_schema') {
      throw new SearchApiTypesenseException('Invalid entity type.');
    }

    if ($route_match->getRawParameter('search_api_index') === NULL) {
      throw new SearchApiTypesenseException(
        'Missing search_api_index parameter.',
      );
    }

    $search_api_index = $route_match->getParameter('search_api_index');
    $index_id = $search_api_index->id();

    $entity = $this
      ->entityTypeManager
      ->getStorage($entity_type_id)
      ->load($index_id);

    if ($entity === NULL) {
      $entity = $this
        ->entityTypeManager
        ->getStorage($entity_type_id)
        ->create([
          'id' => $index_id,
        ]);
    }

    return $entity;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<array-key, mixed> $form
   * @phpstan-return array<array-key, mixed>
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    $form['#tree'] = TRUE;

    $form['warning'] = [
      '#markup' => '<div class="messages messages--warning"><h3>Warning</h3><p>Changing something in this form will change the Typesense schema. This in turn means that the Typesense collection (the index) must be recreated and all content fully re-indexed.</p><p>Please be sure you want to do this, especially on large indexes.</p></div>',
    ];

    $search_api_index = $this
      ->getRouteMatch()
      ->getParameter('search_api_index');

    $form['search_api_index'] = [
      '#type' => 'value',
      '#value' => $search_api_index,
    ];

    /** @var \Drupal\search_api_typesense\Entity\TypesenseSchemaInterface $schema */
    $schema = $this->entity;
    $form['default_sorting_field'] = [
      '#type' => 'select',
      '#options' => [
        '-none-' => $this->t('None'),
      ] + $this->getSortingFieldOptions($search_api_index),
      '#title' => $this->t('Default sorting field'),
      '#description' => $this->t(
        'This field will be used to sort results by default. See the <a href=":typesense_api" target="_blank">Typesense API</a> for more information.',
        [
          ':typesense_api' => 'https://typesense.org/docs/guide/ranking-and-relevance.html#default-ranking-order',
        ],
      ),
      '#default_value' => $schema->getDefaultSortingField(),
    ];

    $form['fields_header'] = [
      '#markup' => '<h2>' . $this->t('Fields') . '</h2>',
    ];

    $fields = $schema->getFields();
    $typesense_fields = $this->getTypesenseFields($search_api_index);
    foreach ($typesense_fields as $field) {
      $field_type = $field->getType();

      $group_id = $field->getFieldIdentifier();

      $form['fields'][$group_id] = [
        '#type' => 'details',
        '#open' => FALSE,
        '#title' => $field->getLabel(),
      ];

      $form['fields'][$group_id]['type'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Datatype'),
        '#description' => $this->t(
          'The Typesense data type of the indexed field. This field is read-only.',
        ),
        '#default_value' => $this->getTypesenseDatatype($field_type),
        '#attributes' => [
          'readonly' => 'readonly',
        ],
        '#size' => 10,
      ];

      $form['fields'][$group_id]['facet'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Facet'),
        '#default_value' => $fields[$group_id]['facet'] ?? FALSE,
        '#description' => $this->t('Enables faceting on the field.'),
      ];

      $form['fields'][$group_id]['optional'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Optional'),
        '#default_value' => $fields[$group_id]['optional'] ?? FALSE,
        '#description' => $this->t(
          'When set to true, the field can have empty, null or missing values.',
        ),
        '#states' => [
          'invisible' => [
            ':input[name="default_sorting_field"]' => [
              'value' => $group_id,
            ],
          ],
          'disabled' => [
            ':input[name="default_sorting_field"]' => [
              'value' => $group_id,
            ],
          ],
        ],
      ];

      $form['fields'][$group_id]['index'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Index'),
        '#default_value' => $fields[$group_id]['index'] ?? TRUE,
        '#description' => $this->t(
          'When set to false, the field will not be indexed in any in-memory index.',
        ),
      ];

      $form['fields'][$group_id]['store'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Store'),
        '#default_value' => $fields[$group_id]['store'] ?? TRUE,
        '#description' => $this->t(
          'When set to false, the field value will not be stored on disk.',
        ),
      ];

      $form['fields'][$group_id]['sort'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Sort'),
        '#default_value' => $fields[$group_id]['sort'] ?? $this->isFieldSortable(
          $field,
        ),
        '#description' => $this->t(
          'When set to true, the field will be sortable.',
        ),
      ];

      $form['fields'][$group_id]['sort_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Sort type'),
        '#options' => [
          'asc' => $this->t('Ascending'),
          'desc' => $this->t('Descending'),
        ],
        '#default_value' => $fields[$group_id]['sort_type'] ?? 'desc',
        '#states' => [
          'invisible' => [
            '[name="fields[' . $group_id . '][sort]' => [
              'checked' => FALSE,
            ],
          ],
        ],
      ];

      $form['fields'][$group_id]['infix'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Infix'),
        '#default_value' => $fields[$group_id]['infix'] ?? FALSE,
        '#description' => $this->t(
          'When set to true, the field value can be infix-searched. Incurs significant memory overhead.',
        ),
      ];

      if ($this->isFieldLocalized($field)) {
        $form['fields'][$group_id]['locale'] = [
          '#type' => 'select',
          '#title' => $this->t('Locale'),
          '#options' => [
            'en' => $this->t('English'),
            'ja' => $this->t('Japanese'),
            'zh' => $this->t('Chinese'),
            'ko' => $this->t('Korean'),
            'th' => $this->t('Thai'),
            'el' => $this->t('Greek'),
            'ru' => $this->t('Russian'),
            'sr' => $this->t('Serbian / Cyrillic'),
            'uk' => $this->t('Ukrainian'),
            'be' => $this->t('Belarusian'),
          ],
          '#default_value' => $fields[$group_id]['locale'] ?? 'en',
          '#description' => $this->t(
            'For configuring language specific tokenization. Choose "en" to support most European languages',
          ),
        ];
      }
      else {
        $form['fields'][$group_id]['locale'] = [
          '#type' => 'value',
          '#value' => NULL,
        ];
      }

      $form['fields'][$group_id]['stem'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Stem'),
        '#default_value' => $fields[$group_id]['stem'] ?? FALSE,
        '#description' => $this->t(
          'Values are stemmed before indexing in-memory. Stem is not compatible with some locales: Japanese, Chinese, Korean, Thai, Ukrainian, Belarusian. See the <a href=":snowball" target="_blank">Snowball</a> stemmer for more information.',
          [
            ':snowball' => 'https://snowballstem.org/algorithms/',
          ],
        ),
      ];

      $form['fields'][$group_id]['weight'] = [
        '#type' => 'number',
        '#title' => $this->t('Query weight'),
        '#description' => $this->t(
          'The weight of the field in the query. Higher weights are given more importance.',
        ),
        '#default_value' => $fields[$group_id]['weight'] ?? 0,
      ];
    }

    if (\count($typesense_fields) === 0) {
      $form['fields']['no_typesense_fields'] = [
        '#markup' => '<p>' . $this->t(
            'No Typesense fields found. Be sure to set fields in the <em>Fields</em> tab to use a <em>Typesense:...</em> type.',
        ) . '</p>',
      ];
    }

    $form['ai_header'] = [
      '#markup' => '<h2>' . \t('AI features') . '</h2>',
    ];

    if ($this->aiModels->isAiSupportAvailable()) {
      $this->addAiForm($form, $schema);
    }
    else {
      $form['no_ai_support'] = [
        '#markup' => '<p>' . $this->t(
          'No AI support available. Be sure to enable the <a href=":ai_module" target="_blank">AI module</a> and at least one supported AI provider.',
          [
            ':ai_module' => 'https://www.drupal.org/project/ai',
          ],
        ) . '</p>',
      ];
    }

    return $form;
  }

  /**
   * Adds the AI form to the schema form.
   *
   * @param array<array-key, mixed> $form
   *   The form.
   * @param \Drupal\search_api_typesense\Entity\TypesenseSchemaInterface $schema
   *   The schema.
   */
  private function addAiForm(
    array &$form,
    TypesenseSchemaInterface $schema,
  ): void {
    $form['enable_embedding'] = [
      '#type' => 'checkbox',
      '#title' => 'Enable embedding',
      '#default_value' => $schema->get('enable_embedding') ?? FALSE,
    ];

    $form['embedding_fields'] = [
      '#type' => 'checkboxes',
      '#title' => \t('Fields used for embedding'),
      '#description' => \t(
        'List of fields used to create chunks. The values from those fields are merged together then split in chunks. Only string and string[] fields are allowed. You may want to exclude fields that are already included in the "Fields to prepend to all chunks" list.',
      ),
      '#options' => $schema->getStringFields(),
      '#default_value' => $schema->get('embedding_fields') ?? [],
      '#states' => [
        'invisible' => [
          ':input[name="enable_embedding"]' => [
            'checked' => FALSE,
          ],
        ],
      ],
    ];

    $form['embedding_model'] = [
      '#type' => 'select',
      '#title' => \t('LLM embedding model'),
      '#description' => \t('The LLM model used for embeddings.'),
      '#options' => $this->aiModels->getEmbeddingModels(),
      '#default_value' => $schema->get('embedding_model') ?? NULL,
      '#states' => [
        'invisible' => [
          ':input[name="enable_embedding"]' => [
            'checked' => FALSE,
          ],
        ],
      ],
    ];

    $form['chunk_prepend_fields'] = [
      '#type' => 'checkboxes',
      '#title' => \t('Fields to prepend to all chunks'),
      '#description' => \t(
        'List of fields whose value is prepended to all chunks, before sending the chunk to the LLM to be embedded. Only string and string[] fields are allowed.',
      ),
      '#options' => $schema->getStringFields(),
      '#default_value' => $schema->get('chunk_prepend_fields') ?? [],
      '#states' => [
        'invisible' => [
          ':input[name="enable_embedding"]' => [
            'checked' => FALSE,
          ],
        ],
      ],
    ];

    $form['chunk_size'] = [
      '#type' => 'number',
      '#title' => \t('Chunk size'),
      '#description' => \t('The size of each chunks.'),
      '#default_value' => $schema->get('chunk_size') ?? 1000,
      '#states' => [
        'invisible' => [
          ':input[name="enable_embedding"]' => [
            'checked' => FALSE,
          ],
        ],
      ],
    ];

    $form['chunk_overlap_size'] = [
      '#type' => 'number',
      '#title' => \t('Chunk overlap size'),
      '#description' => \t(
        'The size of the overlap between consecutive chunks.',
      ),
      '#default_value' => $schema->get('chunk_overlap_size') ?? 10,
      '#states' => [
        'invisible' => [
          ':input[name="enable_embedding"]' => [
            'checked' => FALSE,
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<array-key, mixed> $form
   * @phpstan-return array<array-key, mixed>
   */
  protected function actions(
    array $form,
    FormStateInterface $form_state,
  ): array {
    $actions = parent::actions($form, $form_state);

    $search_api_index = $this
      ->getRouteMatch()
      ->getParameter('search_api_index');

    $typesense_fields = $this->getTypesenseFields($search_api_index);

    if (\count($typesense_fields) === 0) {
      $actions['submit']['#attributes']['disabled'] = 'disabled';
    }

    return $actions;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<array-key, mixed> $form
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $enable_embedding = $form_state->getValue('enable_embedding');
    if ($enable_embedding === FALSE) {
      // @phpstan-ignore-next-line method.notFound
      $this->entity->set('embedding_fields', []);
      // @phpstan-ignore-next-line method.notFound
      $this->entity->set('embedding_model', NULL);
    }

    /** @var \Drupal\search_api\Entity\Index $search_api_index */
    $search_api_index = $form_state->getValue('search_api_index');

    /** @var \Drupal\search_api_typesense\Entity\TypesenseSchemaInterface $original */
    $original = $this->entityTypeManager
      ->getStorage('typesense_schema')
      ->loadUnchanged($this->entity->id());
    /** @var \Drupal\search_api_typesense\Entity\TypesenseSchemaInterface $updated */
    $updated = $this->entity;

    $result = parent::save($form, $form_state);

    // Don't save the index if no changes were made to the schema.
    if ($updated->equals($original)) {
      $this->messenger()->addStatus($this->t('No changes detected.'));
      $form_state->setRedirectUrl(
        Url::fromRoute(
          'entity.search_api_index.canonical',
          ['search_api_index' => $search_api_index->id()],
        ),
      );

      return 0;
    }

    // Save the index to force a re-index.
    $search_api_index->save();

    $this->messenger()->addStatus($this->t('Schema updated.'));
    $form_state->setRedirectUrl(
      Url::fromRoute(
        'entity.search_api_index.canonical',
        ['search_api_index' => $search_api_index->id()],
      ),
    );

    return $result;
  }

  /**
   * Gets the Typesense fields from the index.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index.
   *
   * @return \Drupal\search_api\Item\FieldInterface[]
   *   The Typesense fields.
   */
  private function getTypesenseFields(IndexInterface $index): array {
    return \array_filter(
      $index->getFields(),
      static function (FieldInterface $field) {
        return \str_starts_with($field->getType(), 'typesense_');
      },
    );
  }

  /**
   * Gets the native Typesense datatype from the module value.
   *
   * @param string $search_api_typesense_datatype
   *   The search_api_typesense datatype.
   *
   * @return string
   *   The Typesense datatype.
   */
  private function getTypesenseDatatype(
    string $search_api_typesense_datatype,
  ): string {
    return \str_replace('typesense_', '', $search_api_typesense_datatype);
  }

  /**
   * Gets array of field ids and names for all numeric fields in this index.
   *
   * Typesense only sorts by numeric values, so this function returns only the
   * fields whose declared datatype is one of int32, int32[], float, or float[].
   *
   * The returned value is suitable for use in #select form api objects'
   * #options arrays.
   *
   * @return array
   *   An array of field ids and names for all numeric fields in this index.
   */
  private function getSortingFieldOptions(
    IndexInterface $search_api_index,
  ): array {
    $sorting_field_options = [];

    foreach ($search_api_index->getFields() as $field) {
      $field_type = $field->getType();
      if (\preg_match('/^typesense_(float|int32)/', $field_type)) {
        $sorting_field_options[$field->getFieldIdentifier()] = \sprintf(
          '%s (%s)',
          $field->getLabel(),
          $this->getTypesenseDatatype($field_type),
        );
      }
    }

    return $sorting_field_options;
  }

  /**
   * Determines if a field is sortable.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   The field.
   *
   * @return bool
   *   TRUE if the field is sortable, FALSE otherwise.
   */
  private function isFieldSortable(FieldInterface $field): bool {
    return \preg_match('/^typesense_(float|int32)/', $field->getType()) === 1;
  }

  /**
   * Determines if a field is localized.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   The field.
   *
   * @return bool
   *   TRUE if the field is localized, FALSE otherwise.
   */
  private function isFieldLocalized(FieldInterface $field): bool {
    return \preg_match('/^typesense_(string)/', $field->getType()) === 1;
  }

}
