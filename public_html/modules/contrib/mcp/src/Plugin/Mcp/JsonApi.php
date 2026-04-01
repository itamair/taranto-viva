<?php

declare(strict_types=1);

namespace Drupal\mcp\Plugin\Mcp;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp\Attribute\Mcp;
use Drupal\mcp\Plugin\McpPluginBase;
use Drupal\mcp\ServerFeatures\Tool;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Plugin implementation of the JSON:API MCP provider.
 */
#[Mcp(
  id: 'jsonapi',
  name: new TranslatableMarkup('JSON:API'),
  description: new TranslatableMarkup(
    'Search and retrieve content using Drupal JSON:API'
  )
)]
class JsonApi extends McpPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The HTTP kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * The JSON:API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface|null
   */
  protected $resourceTypeRepository;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The JSON:API base path.
   *
   * @var string
   */
  protected $jsonApiBasePath;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    $instance = parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition,
    );

    $instance->httpKernel = $container->get('http_kernel');
    $instance->resourceTypeRepository = $container->get(
      'jsonapi.resource_type.repository',
      ContainerInterface::NULL_ON_INVALID_REFERENCE
    );
    $instance->requestStack = $container->get('request_stack');
    $instance->currentUser = $container->get('current_user');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    // Get JSON:API base path safely.
    try {
      $instance->jsonApiBasePath = $container->getParameter('jsonapi.base_path');
    }
    catch (\Exception $e) {
      $instance->jsonApiBasePath = '/jsonapi';
    }
    $instance->moduleHandler = $container->get('module_handler');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function checkRequirements(): bool {
    return $this->resourceTypeRepository !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequirementsDescription(): string {
    if (!$this->checkRequirements()) {
      return (string) $this->t('The JSON:API module must be installed and enabled.');
    }
    return '';
  }

  /**
   * Check if jsonapi_schema module is enabled.
   */
  protected function isSchemaModuleEnabled(): bool {
    return $this->moduleHandler->moduleExists('jsonapi_schema');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $config = parent::defaultConfiguration();
    $config['enabled'] = FALSE;
    $config['config']['allowed_resource_types'] = [];

    return $config;
  }

  /**
   * Get allowed resource types based on configuration.
   *
   * @return array
   *   Array of allowed resource type names.
   */
  protected function getAllowedResourceTypes(): array {
    $resource_types = [];

    if (!$this->resourceTypeRepository) {
      return $resource_types;
    }

    $config = $this->getConfiguration();
    $allowed_types = $config['config']['allowed_resource_types'] ?? [];

    foreach ($this->resourceTypeRepository->all() as $resource_type) {
      if (!$resource_type->isInternal()) {
        $type_name = $resource_type->getTypeName();
        // Check if type is allowed.
        if (empty($allowed_types) || in_array($type_name, $allowed_types)) {
          $resource_types[] = $type_name;
        }
      }
    }

    return $resource_types;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ): array {
    $config = $this->getConfiguration();

    $form['notice'] = [
      '#type'   => 'markup',
      '#markup' => '<div class="messages messages--warning">' .
      $this->t(
          '<strong>Note:</strong> This plugin provides access to your Drupal content via JSON:API. Only enable if you need MCP clients to access your content.'
      ) .
      '</div>',
    ];

    // Add notice about jsonapi_schema module.
    if (!$this->isSchemaModuleEnabled()) {
      $form['schema_notice'] = [
        '#type'   => 'markup',
        '#markup' => '<div class="messages messages--info">' .
        $this->t(
            '<strong>Tip:</strong> Install and enable the JSON:API Schema module to get access to the schema tool, which helps LLMs understand available fields and relationships.'
        ) .
        '</div>',
      ];
    }

    // Get all available resource types for form options.
    $resource_types = [];
    if ($this->resourceTypeRepository) {
      foreach ($this->resourceTypeRepository->all() as $resource_type) {
        if (!$resource_type->isInternal()) {
          $type_name = $resource_type->getTypeName();
          $resource_types[$type_name] = $type_name;
        }
      }
    }

    $form['allowed_resource_types'] = [
      '#type'          => 'checkboxes',
      '#title'         => $this->t('Allowed Resource Types'),
      '#description'   => $this->t(
        'Select which resource types can be accessed. Leave empty to allow all.'
      ),
      '#options'       => $resource_types,
      '#default_value' => $config['config']['allowed_resource_types'] ?? [],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $this->configuration['config']['allowed_resource_types'] = array_filter(
        $form_state->getValue(['config', 'allowed_resource_types']) ?? []
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTools(): array {
    $tools = [];

    $tools[] = new Tool(
      name: 'jsonapi_read',
      description: "
        Retrieve entities via JSON:API. Supports filtering, pagination, and including related resources.
        If jsonapi_schema tool is available, use it to understand fields and relationships and only then use this tool.
        Only request necessary fields to optimize performance and reduce token usage.
      ",
      inputSchema: $this->getReadToolSchema(),
    );

    if ($this->isSchemaModuleEnabled()) {
      $tools[] = new Tool(
        name: 'jsonapi_schema',
        description: 'Retrieve JSON Schema for resource types. Helps understand available fields, relationships, and data types.',
        inputSchema: $this->getSchemaToolSchema(),
      );
    }

    return $tools;
  }

  /**
   * Get the schema for the read tool.
   */
  protected function getReadToolSchema(): array {
    $resource_types = $this->getAllowedResourceTypes();

    return [
      'type'       => 'object',
      'properties' => [
        'resource_type' => [
          'type'        => 'string',
          'enum'        => $resource_types,
          'description' => 'Resource type (e.g., node--article, user--user)',
        ],
        'uuid'          => [
          'type'        => 'string',
          'description' => 'UUID of specific entity (optional, omit for collection)',
        ],
        'filters'       => $this->getFilterSchema(),
        'include'       => [
          'type'        => 'string',
          'description' => 'Related resources to include (comma-separated field names)',
        ],
        'page'          => [
          'type'       => 'object',
          'properties' => [
            'limit'  => [
              'type'    => 'integer',
              'default' => 10,
              'minimum' => 1,
              'maximum' => 50,
            ],
            'offset' => [
              'type'    => 'integer',
              'default' => 0,
              'minimum' => 0,
            ],
          ],
        ],
        'sort'          => [
          'type'        => 'string',
          'description' => 'Sort order (e.g., "-created" for newest first, "title" for alphabetical)',
        ],
        'fields'        => [
          'type'                 => 'object',
          'description'          => 'Sparse fieldsets: specify which fields to include for each resource type',
          'additionalProperties' => [
            'type'        => 'string',
            'description' => 'Comma-separated list of fields to include for this resource type',
          ],
          'examples'             => [
            ['node--article' => 'title,body,created,uid'],
            ['user--user' => 'name,mail'],
          ],
        ],
      ],
      'required'   => ['resource_type'],
    ];
  }

  /**
   * Get the filter schema for JSON:API.
   */
  protected function getFilterSchema(): array {
    return [
      'type'                 => 'object',
      'description'          => 'JSON:API filters supporting full specification including operators, groups, and relationships',
      'additionalProperties' => [
        'oneOf' => [
          // Simple filter (shorthand for equality)
          ['type' => 'string'],
          ['type' => 'number'],
          ['type' => 'boolean'],
          ['type' => 'null'],
          // Array for IN/NOT IN operators.
          [
            'type'  => 'array',
            'items' => [
              'oneOf' => [
                ['type' => 'string'],
                ['type' => 'number'],
                ['type' => 'boolean'],
              ],
            ],
          ],
          // Condition object.
          [
            'type'                 => 'object',
            'properties'           => [
              'path'     => [
                'type'        => 'string',
                'description' => 'Field path (e.g., "title", "uid.name", "field_tags.name")',
              ],
              'value'    => [
                'oneOf' => [
                  ['type' => 'string'],
                  ['type' => 'number'],
                  ['type' => 'boolean'],
                  ['type' => 'null'],
                  [
                    'type'  => 'array',
                    'items' => [
                      'oneOf' => [
                        ['type' => 'string'],
                        ['type' => 'number'],
                        ['type' => 'boolean'],
                      ],
                    ],
                  ],
                ],
              ],
              'operator' => [
                'type'    => 'string',
                'enum'    => [
                  '=',
                  '<>',
                  '>',
                  '>=',
                  '<',
                  '<=',
                  'STARTS_WITH',
                  'CONTAINS',
                  'ENDS_WITH',
                  'IN',
                  'NOT IN',
                  'BETWEEN',
                  'NOT BETWEEN',
                  'IS NULL',
                  'IS NOT NULL',
                ],
                'default' => '=',
              ],
              'memberOf' => [
                'type'        => 'string',
                'description' => 'Group ID this condition belongs to',
              ],
            ],
            'additionalProperties' => FALSE,
          ],
          // Group object.
          [
            'type'                 => 'object',
            'properties'           => [
              'group' => [
                'type'       => 'object',
                'properties' => [
                  'conjunction' => [
                    'type'    => 'string',
                    'enum'    => ['AND', 'OR'],
                    'default' => 'AND',
                  ],
                  'memberOf'    => [
                    'type'        => 'string',
                    'description' => 'Parent group ID',
                  ],
                ],
              ],
            ],
            'additionalProperties' => FALSE,
          ],
        ],
      ],
      'examples'             => [
        [
          'title'  => 'Simple filter',
          'filter' => ['status' => '1'],
        ],
        [
          'title'  => 'Filter with operator',
          'filter' => [
            'created' => [
              'value'    => '2023-01-01',
              'operator' => '>',
            ],
          ],
        ],
        [
          'title'  => 'Filter by relationship',
          'filter' => ['uid.name' => 'admin'],
        ],
        [
          'title'  => 'Complex filter with groups',
          'filter' => [
            'or-group'       => [
              'group' => ['conjunction' => 'OR'],
            ],
            'status-filter'  => [
              'path'     => 'status',
              'value'    => '1',
              'memberOf' => 'or-group',
            ],
            'promote-filter' => [
              'path'     => 'promote',
              'value'    => '1',
              'memberOf' => 'or-group',
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Get the schema for the schema tool.
   */
  protected function getSchemaToolSchema(): array {
    $resource_types = $this->getAllowedResourceTypes();

    return [
      'type'       => 'object',
      'properties' => [
        'resource_type' => [
          'type'        => 'string',
          'enum'        => $resource_types,
          'description' => 'Resource type to get schema for (e.g., node--article, user--user)',
        ],
        'schema_type'   => [
          'type'        => 'string',
          'enum'        => ['resource', 'collection', 'entrypoint'],
          'default'     => 'resource',
          'description' => 'Type of schema to retrieve: resource (object structure), collection (list structure), or entrypoint (all available resources)',
        ],
      ],
      'required'   => ['resource_type'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function executeTool(string $toolId, mixed $arguments): array {
    $sanitizedReadName = $this->sanitizeToolName('jsonapi_read');
    $sanitizedSchemaName = $this->sanitizeToolName('jsonapi_schema');

    if ($toolId === $sanitizedReadName) {
      return $this->executeReadTool($arguments);
    }

    if ($toolId === $sanitizedSchemaName) {
      return $this->executeSchemaTool($arguments);
    }

    throw new \InvalidArgumentException('Tool not found: ' . $toolId);
  }

  /**
   * Execute the read tool.
   */
  protected function executeReadTool(array $arguments): array {
    if (!isset($arguments['resource_type'])) {
      throw new \InvalidArgumentException('Resource type is required');
    }
    $resource_type = $arguments['resource_type'];
    $uuid = $arguments['uuid'] ?? NULL;

    $config = $this->getConfiguration();
    $allowed_types = $config['config']['allowed_resource_types'] ?? [];
    if (!empty($allowed_types) && !in_array($resource_type, $allowed_types)) {
      throw new \InvalidArgumentException(
        'Resource type not allowed: ' . $resource_type
      );
    }

    $path = '/' . str_replace('--', '/', $resource_type);
    if ($uuid) {
      $path .= '/' . $uuid;
    }

    $query = [];
    if (isset($arguments['filters'])) {
      $query['filter'] = $arguments['filters'];
    }
    if (isset($arguments['include'])) {
      $query['include'] = $arguments['include'];
    }
    if (isset($arguments['page'])) {
      $query['page'] = $arguments['page'];
    }
    if (isset($arguments['sort'])) {
      $query['sort'] = $arguments['sort'];
    }
    if (isset($arguments['fields'])) {
      $query['fields'] = $arguments['fields'];
    }

    try {
      $data = $this->makeInternalRequest($path, $query);

      return [
        [
          'type' => 'text',
          'text' => json_encode(
            $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
          ),
        ],
      ];
    }
    catch (\Exception $e) {
      throw new \Exception('JSON:API request failed: ' . $e->getMessage());
    }
  }

  /**
   * Execute the schema tool.
   */
  protected function executeSchemaTool(array $arguments): array {
    if (!isset($arguments['resource_type'])) {
      throw new \InvalidArgumentException('Resource type is required');
    }
    $resource_type = $arguments['resource_type'];
    $schema_type = $arguments['schema_type'] ?? 'resource';

    $config = $this->getConfiguration();
    $allowed_types = $config['config']['allowed_resource_types'] ?? [];
    if (!empty($allowed_types) && !in_array($resource_type, $allowed_types)) {
      throw new \InvalidArgumentException(
        'Resource type not allowed: ' . $resource_type
      );
    }

    // Determine the appropriate schema path.
    $path = match ($schema_type) {
      'entrypoint' => '/schema',
      'collection' => '/' . str_replace('--', '/', $resource_type)
        . '/collection/schema',
      default => '/' . str_replace('--', '/', $resource_type) . '/resource/schema',
    };

    try {
      $data = $this->makeInternalRequest($path);

      return [
        [
          'type' => 'text',
          'text' => json_encode(
            $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
          ),
        ],
      ];
    }
    catch (\Exception $e) {
      throw new \Exception('JSON:API Schema request failed: ' . $e->getMessage());
    }
  }

  /**
   * Make an internal JSON:API request.
   */
  protected function makeInternalRequest(
    string $path,
    array $query = [],
  ): array {
    $current_request = $this->requestStack->getCurrentRequest();

    $url = $this->jsonApiBasePath . $path;
    if ($query) {
      $url .= '?' . http_build_query($query);
    }

    $request = Request::create(
      $url,
      'GET',
      [],
      $current_request->cookies->all(),
      [],
      $current_request->server->all()
    );

    if ($session = $current_request->getSession()) {
      $request->setSession($session);
    }

    $request->headers->set('Accept', 'application/vnd.api+json');

    $response = $this->httpKernel->handle(
      $request, HttpKernelInterface::SUB_REQUEST
    );

    if ($response->getStatusCode() >= 400) {
      $content = $response->getContent();
      $error_data = json_decode($content, TRUE);
      $error_message = $error_data['errors'][0]['detail'] ?? 'Unknown error';

      throw new \Exception('JSON:API error: ' . $error_message);
    }

    // Parse response.
    $content = $response->getContent();

    return json_decode($content, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function hasAccess(): AccessResult {
    $parentAccess = parent::hasAccess();
    if (!$parentAccess->isAllowed()) {
      return $parentAccess;
    }

    return AccessResult::allowedIfHasPermission($this->currentUser, 'access content');
  }

}
