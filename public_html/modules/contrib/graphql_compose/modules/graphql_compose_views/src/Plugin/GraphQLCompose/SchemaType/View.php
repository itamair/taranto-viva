<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_views\Plugin\GraphQLCompose\SchemaType;

use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeSchemaTypeBase;
use Drupal\graphql_compose_views\Plugin\views\display\GraphQL;
use Drupal\graphql_compose_views\Plugin\views\row\GraphQLFieldRow;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Views;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeSchemaType(
 *   id = "View",
 * )
 */
class View extends GraphQLComposeSchemaTypeBase {

  /**
   * {@inheritdoc}
   *
   * Add dynamic view types that use View interface.
   */
  public function getTypes(): array {
    $types = [];

    $types[] = new InterfaceType([
      'name' => $this->getPluginId(),
      'description' => (string) $this->t('Views represent collections of curated data from the CMS.'),
      'fields' => fn() => [
        'id' => [
          'type' => Type::nonNull(Type::id()),
          'description' => (string) $this->t('The ID of the view.'),
        ],
        'view' => [
          'type' => Type::nonNull(Type::string()),
          'description' => (string) $this->t('The machine name of the view.'),
        ],
        'display' => [
          'type' => Type::nonNull(Type::string()),
          'description' => (string) $this->t('The machine name of the display.'),
        ],
        'langcode' => [
          'type' => Type::string(),
          'description' => (string) $this->t('The language code of the view.'),
        ],
        'label' => [
          'type' => Type::string(),
          'description' => (string) $this->t('The human friendly label of the view.'),
        ],
        'description' => [
          'type' => Type::string(),
          'description' => (string) $this->t('The description of the view.'),
        ],
        'pageInfo' => [
          'type' => Type::nonNull(static::type('ViewPageInfo')),
          'description' => (string) $this->t('Information about the page in the view.'),
        ],
      ],
    ]);

    $viewStorage = $this->entityTypeManager->getStorage('view');

    $union_types = [];

    foreach (Views::getApplicableViews('graphql_display') as $applicable_view) {
      // Destructure view and display ids.
      [$view_id, $display_id] = $applicable_view;

      if (!$view_entity = $viewStorage->load($view_id)) {
        continue;
      }

      /** @var \Drupal\views\ViewEntityInterface|null $view_entity */
      $view = $view_entity->getExecutable();
      $view->setDisplay($display_id);
      $view->initHandlers();

      /** @var \Drupal\graphql_compose_views\Plugin\views\display\GraphQL $display */
      $display = $view->getDisplay();

      $exposed_filters = $display->usesExposed();
      $result_type = $display->getGraphQlResultType();

      // Get the description for the view.
      $view_description = $view->storage->get('description') ?: $this->t('Result for view @view display @display.', [
        '@view' => $view_id,
        '@display' => $display_id,
      ]);

      // Create type for view base on View Interface.
      $types[] = new ObjectType([
        'name' => $display->getGraphQlResultName(),
        'description' => (string) $view_description,
        'interfaces' => fn () => [static::type('View')],
        'fields' => fn() => array_filter([
          'id' => [
            'type' => Type::nonNull(Type::id()),
            'description' => (string) $this->t('The ID of the view.'),
          ],
          'view' => [
            'type' => Type::nonNull(Type::string()),
            'description' => (string) $this->t('The machine name of the view.'),
          ],
          'display' => [
            'type' => Type::nonNull(Type::string()),
            'description' => (string) $this->t('The machine name of the display.'),
          ],
          'langcode' => [
            'type' => Type::string(),
            'description' => (string) $this->t('The language code of the view.'),
          ],
          'label' => [
            'type' => Type::string(),
            'description' => (string) $this->t('The human friendly label of the view.'),
          ],
          'description' => [
            'type' => Type::string(),
            'description' => (string) $this->t('The description of the view.'),
          ],
          'pageInfo' => [
            'type' => Type::nonNull(static::type('ViewPageInfo')),
            'description' => (string) $this->t('Information about the page in the view.'),
          ],
          'filters' => $exposed_filters ? [
            'type' => Type::nonNull(Type::listOf(static::type('ViewFilter'))),
            'description' => (string) $this->t('Exposed filters for the view.'),
          ] : [],
          'results' => [
            'type' => Type::nonNull(Type::listOf(Type::nonNull(static::type($result_type)))),
            'description' => (string) $this->t('The results of the view.'),
          ],
        ]),
      ]);

      // Keep a union of all the view types.
      $union_types[] = $display->getGraphQlResultName();

      $types = [
        ...$types,
        ...$this->getSortTypes($display),
        ...$this->getFieldTypes($display),
        ...$this->getUnionTypes($display),
        ...$this->getFilterTypes($display),
        ...$this->getContextualFilterTypes($display),
      ];

      $view->destroy();
    }

    // Create type for view base on View Interface.
    $types[] = new UnionType([
      'name' => 'ViewResultUnion',
      'description' => (string) $this->t('All available view result types.'),
      'types' => fn() => array_map(
        static::type(...),
        $union_types ?: ['UnsupportedType']
      ),
    ]);

    return $types;
  }

  /**
   * {@inheritdoc}
   */
  public function getExtensions(): array {
    $extensions = parent::getExtensions();

    $viewStorage = $this->entityTypeManager->getStorage('view');

    foreach (Views::getApplicableViews('graphql_display') as $applicable_view) {
      // Destructure view and display ids.
      [$view_id, $display_id] = $applicable_view;

      if (!$view_entity = $viewStorage->load($view_id)) {
        continue;
      }

      /** @var \Drupal\views\ViewEntityInterface|null $view_entity */
      $view = $view_entity->getExecutable();
      $view->setDisplay($display_id);

      /** @var \Drupal\graphql_compose_views\Plugin\views\display\GraphQL $display */
      $display = $view->getDisplay();

      if (!$display->getOption('graphql_query_exposed')) {
        continue;
      }

      // Get the description for the view.
      $view_description = $view->storage->get('description') ?: $this->t('Query for view @view display @display.', [
        '@view' => $view_id,
        '@display' => $display_id,
      ]);

      $query = $display->getGraphQlQueryName();
      $type = $display->getGraphQlResultName();

      $args = [
        ...$this->getSortArgs($display),
        ...$this->getPagerArgs($display),
        ...$this->getFilterArgs($display),
        ...$this->getContextualFilterArgs($display),
      ];

      $extensions[] = new ObjectType([
        'name' => 'Query',
        'fields' => fn() => [
          $query => [
            'type' => static::type($type),
            'description' => (string) $view_description,
            'args' => $args,
          ],
        ],
      ]);

      $view->destroy();
    }

    return $extensions;
  }

  /**
   * Get union types for entity row display.
   *
   * @param \Drupal\graphql_compose_views\Plugin\views\display\GraphQL $display
   *   The view display.
   *
   * @return \GraphQL\Type\Definition\UnionType[]
   *   Union types.
   */
  public function getUnionTypes(GraphQL $display) {
    $types = [];

    if ($display->hasGraphQlUnionTypes()) {
      $union_types = $display->getGraphQlUnionTypes();

      $types[] = new UnionType([
        'name' => $display->getGraphQlRowName(),
        'description' => (string) $this->t('All available types for view result row.'),
        'types' => fn() => array_map(
          static::type(...),
          $union_types,
        ),
      ]);
    }

    return $types;
  }

  /**
   * Get sort types for display.
   *
   * @param \Drupal\graphql_compose_views\Plugin\views\display\GraphQL $display
   *   The view display.
   *
   * @return \GraphQL\Type\Definition\EnumType[]
   *   Sort types.
   */
  private function getSortTypes(GraphQL $display): array {
    $types = [];

    $exposed_sorts = array_filter(
      $display->getOption('sorts') ?: [],
      fn ($filter) => !empty($filter['exposed'])
    );

    if (!empty($exposed_sorts)) {
      $types[] = new EnumType([
        'name' => $display->getGraphQlSortInputName(),
        'values' => $display->getGraphQlSortEnums(),
      ]);
    }

    return $types;
  }

  /**
   * Get sort args for display.
   *
   * @param \Drupal\graphql_compose_views\Plugin\views\display\GraphQL $display
   *   The view display.
   *
   * @return array
   *   Sort args for GraphQL.
   */
  private function getSortArgs(GraphQL $display): array {
    $args = [];

    // Pagination enabled at a set limit.
    $exposed_sorts = array_filter(
      $display->getOption('sorts') ?: [],
      fn ($filter) => !empty($filter['exposed'])
    );

    if ($exposed_sorts) {
      $args['sortKey'] = [
        'type' => static::type($display->getGraphQlSortInputName()),
        'description' => (string) $this->t('Sort the view by this key.'),
      ];
    }

    if ($display->getOption('exposed_form')['options']['expose_sort_order'] ?? FALSE) {
      $args['sortDir'] = [
        'type' => static::type('SortDirection'),
        'description' => (string) $this->t('Sort the view direction.'),
      ];
    }

    return $args;
  }

  /**
   * Get pager types for display.
   *
   * @param \Drupal\graphql_compose_views\Plugin\views\display\GraphQL $display
   *   The view display.
   *
   * @return array
   *   Pager types.
   */
  private function getPagerArgs(GraphQL $display): array {

    if (!in_array($display->getOption('pager')['type'] ?? '', ['full', 'mini'])) {
      return [];
    }

    $args = [];

    $args['page'] = [
      'type' => Type::int(),
      'description' => (string) $this->t('The page number to display.'),
      'defaultValue' => 0,
    ];

    $pager_options = $display->getOption('pager')['options'] ?? [];

    // Allow setting items per page.
    if ($pager_options['expose']['items_per_page'] ?? FALSE) {
      $args['pageSize'] = [
        'type' => Type::int(),
        'description' => (string) $this->t('@label. Allowed values are: @input.', [
          '@label' => $pager_options['expose']['items_per_page_label'],
          '@input' => $pager_options['expose']['items_per_page_options'],
        ]),
        'defaultValue' => $pager_options['items_per_page'] ?? 10,
      ];
    }

    if ($pager_options['expose']['offset'] ?? FALSE) {
      $args['offset'] = [
        'type' => Type::int(),
        'description' => (string) $this->t('@label. The number of items skipped from beginning of this view.', [
          '@label' => $pager_options['expose']['offset_label'],
        ]),
        'defaultValue' => $pager_options['offset'] ?? 0,
      ];
    }

    return $args;
  }

  /**
   * Get field types for display.
   *
   * @param \Drupal\graphql_compose_views\Plugin\views\display\GraphQL $display
   *   The view display.
   *
   * @return \GraphQL\Type\Definition\Type[]
   *   Field types.
   */
  private function getFieldTypes(GraphQL $display): array {
    $types = [];
    $type_fields = [];

    $row_plugin = $display->getPlugin('row');
    if (!$row_plugin instanceof GraphQLFieldRow) {
      return [];
    }

    $fields = $display->view->display_handler->getOption('fields') ?: [];

    // Filter out the excluded fields.
    $fields = array_filter(
      $fields,
      fn(array $field) => empty($field['exclude'])
    );

    foreach ($fields as $id => $field) {
      // Alias and type set by user.
      $field_alias = $row_plugin->getAlias($id);
      $field_type = $row_plugin->getType($id);

      // Raw output could be anything.
      // We're going to need a custom scalar and dump junk into it.
      if ($field_type === 'Scalar') {
        $types[] = $custom_scalar = new CustomScalarType([
          'name' => $display->getGraphQlName($field_alias . 'Field'),
          'description' => (string) $this->t('Output of @field. Contents unknown.', [
            '@field' => $field_alias,
          ]),
        ]);

        $type_fields[$field_alias] = $custom_scalar;
      }
      else {
        // Map the type to a new GraphQL type.
        $type_fields[$field_alias] = call_user_func([Type::class, $field_type]);
      }
    }

    $types[] = new ObjectType([
      'name' => $display->getGraphQlRowName(),
      'description' => (string) $this->t('Result for view @view display @display.', [
        '@view' => $display->view->id(),
        '@display' => $display->display['id'],
      ]),
      'fields' => fn() => $type_fields,
    ]);

    return $types;
  }

  /**
   * Get filter types for display.
   *
   * @param \Drupal\graphql_compose_views\Plugin\views\display\GraphQL $display
   *   The view display.
   *
   * @return \GraphQL\Type\Definition\InputObjectType[]
   *   Filter types.
   */
  private function getFilterTypes(GraphQL $display): array {

    $types = [];
    $filter_fields = [];

    /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase[] $exposed_filters */
    $exposed_filters = array_filter(
      $display->getHandlers('filter'),
      fn (FilterPluginBase $filter) => $filter->isExposed()
    );

    foreach ($exposed_filters as $filter) {
      $info = $filter->exposedInfo();

      $required = $filter->isAGroup()
        ? !$filter->options['group_info']['optional']
        : $filter->options['expose']['required'];

      $multiple = $filter->isAGroup()
        ? $filter->options['group_info']['multiple']
        : $filter->options['expose']['multiple'];

      $between = in_array($filter->operator, ['between', 'not between']);

      switch ($filter->getPluginId()) {
        case 'boolean':
        case 'search_api_boolean':
          $type = Type::boolean();
          break;

        case 'numeric':
        case 'search_api_numeric':
          $type = $between
            ? static::type('BetweenFloatInput')
            : Type::float();
          break;

        default:
          $type = $between
            ? static::type('BetweenStringInput')
            : Type::string();
          break;
      }

      if ($multiple) {
        $type = Type::listOf($type);
      }

      if ($required) {
        $type = Type::nonNull($type);
      }

      $filter_fields[$info['value']] = [
        'type' => $type,
        'description' => (string) $this->t('@label @description', [
          '@label' => $info['label'] ?? '',
          '@description' => $info['description'] ?? '',
        ]),
      ];
    }

    if (!empty($filter_fields)) {
      $types[] = new InputObjectType([
        'name' => $display->getGraphQlFilterInputName(),
        'fields' => fn() => $filter_fields,
      ]);
    }

    return $types;
  }

  /**
   * Get filter arguments for display.
   *
   * @param \Drupal\graphql_compose_views\Plugin\views\display\GraphQL $display
   *   The view display.
   *
   * @return array
   *   Filter arguments.
   */
  private function getFilterArgs(GraphQL $display): array {

    $args = [];

    /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase[] $exposed_filters */
    $exposed_filters = array_filter(
      $display->getHandlers('filter'),
      fn (FilterPluginBase $filter) => $filter->isExposed()
    );

    $required_filters = array_filter(
      $exposed_filters,
      fn (FilterPluginBase $filter) => $filter->isAGroup()
        ? !$filter->options['group_info']['optional']
        : $filter->options['expose']['required']
    );

    if ($exposed_filters) {
      $type = static::type($display->getGraphQlFilterInputName());

      if ($required_filters) {
        $type = Type::nonNull($type);
      }

      $args['filter'] = [
        'type' => $type,
        'description' => (string) $this->t('Filter the view.'),
      ];
    }

    return $args;
  }

  /**
   * Get contextual filter types for display.
   *
   * @param \Drupal\graphql_compose_views\Plugin\views\display\GraphQL $display
   *   The view display.
   *
   * @return \GraphQL\Type\Definition\InputObjectType[]
   *   Contextual filter types.
   */
  private function getContextualFilterTypes(GraphQL $display): array {

    $types = [];

    $contextual_filters = $display->getOption('arguments') ?: [];

    if ($contextual_filters) {
      $types[] = new InputObjectType([
        'name' => $display->getGraphQlContextualFilterInputName(),
        'fields' => fn() => array_map(
          fn () => Type::string(),
          $contextual_filters,
        ),
      ]);
    }

    return $types;
  }

  /**
   * Get contextual filter arguments for display.
   *
   * @param \Drupal\graphql_compose_views\Plugin\views\display\GraphQL $display
   *   The view display.
   *
   * @return array
   *   Contextual filter arguments.
   */
  private function getContextualFilterArgs(GraphQL $display): array {
    $args = [];

    $contextual_filters = $display->getOption('arguments') ?: [];

    if ($contextual_filters) {
      $args['contextualFilter'] = [
        'type' => static::type($display->getGraphQlContextualFilterInputName()),
        'description' => (string) $this->t('Contextual filters for the view.'),
      ];
    }

    return $args;
  }

}
