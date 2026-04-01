<?php

namespace Drupal\mcp\Plugin\Mcp;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\mcp\ServerFeatures\ToolAnnotations;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp\Attribute\Mcp;
use Drupal\mcp\Plugin\McpPluginBase;
use Drupal\mcp\ServerFeatures\Tool;
use Drupal\tool\TypedData\InputDefinitionInterface;
use Drupal\tool\TypedData\InputDefinitionRefinerInterface;
use Drupal\tool\TypedData\ListInputDefinition;
use Drupal\tool\TypedData\MapContextDefinition;
use Drupal\tool\TypedData\MapInputDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the mcp.
 */
#[Mcp(
  id: 'tools',
  name: new TranslatableMarkup('Tool API'),
  description: new TranslatableMarkup(
    'Provides Tool API tools'
  ),
)]
class ToolApi extends McpPluginBase {

  const TYPE_INPUT = 'input';

  const TYPE_OUTPUT = 'output';

  /**
   * The tool manager.
   *
   * @var \Drupal\tool\Tool\ToolManager|null
   */
  protected $toolManager;

  /**
   * Serializer.
   *
   * @var \Drupal\serialization\Serializer\Serializer
   */
  protected $serializer;

  /**
   * Temp Store.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStore;

  /**
   * {@inheritDoc}
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

    $instance->toolManager = $container->get(
      'plugin.manager.tool', ContainerInterface::NULL_ON_INVALID_REFERENCE
    );
    $instance->serializer = $container->get('serializer');
    $instance->tempStore = $container->get('tempstore.private');

    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function checkRequirements(): bool {
    return $this->toolManager !== NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function getRequirementsDescription(): string {
    if (!$this->checkRequirements()) {
      return $this->t('The Tool API module with and at least one tool is required.');
    }

    return '';
  }

  /**
   * {@inheritDoc}
   */
  public function getTools(): array {
    $tools = [];
    foreach ($this->toolManager->getDefinitions() as $name => $definition) {
      $tool = new Tool(
        name: str_replace(':', '___', $name),
        description: (string) $definition->getDescription(),
        inputSchema: $this->getContextDefinitionSchema($definition->getInputDefinitions(), self::TYPE_INPUT),
        title: (string) $definition->getLabel(),
      );
      if ($output_definitions = $definition->getOutputDefinitions()) {
        $tool->outputSchema = $this->getContextDefinitionSchema($output_definitions, self::TYPE_OUTPUT);
      }

      if ($definition->isDestructive()) {
        $tool->annotations = new ToolAnnotations(
          destructiveHint: TRUE,
        );
      }
      $tools[] = $tool;
    }
    return $tools;
  }

  /**
   * Gets the JSON schema for the given context definitions.
   *
   * @param \Drupal\Core\Plugin\Context\ContextDefinitionInterface[] $definitions
   *   The context definitions.
   * @param string $type
   *   The type: input or output.
   *
   * @return array
   *   The JSON schema.
   *
   * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
   */
  public function getContextDefinitionSchema(array $definitions, string $type): array {
    $input_schema = new MapContextDefinition(
      data_type: 'map',
      required: TRUE,
      property_definitions: $this->prepareContextDefinitions($definitions, $type)
    );
    return $this->serializer->normalize($input_schema, 'json_schema');
  }

  /**
   * Prepares context definitions by converting entity definitions to string.
   *
   * @param \Drupal\Core\Plugin\Context\ContextDefinitionInterface[] $definitions
   *   The context definitions.
   * @param string $type
   *   The type: input or output.
   *
   * @return \Drupal\Core\Plugin\Context\ContextDefinitionInterface[]
   *   The prepared context definitions.
   */
  public function prepareContextDefinitions(array $definitions, string $type): array {
    foreach ($definitions as $name => $definition) {
      if ($definition->getDataType() === 'entity' || $definition instanceof EntityContextDefinition) {
        $description = (string) $definition->getDescription();
        if ($type === self::TYPE_INPUT) {
          $description = rtrim($description, '.');
          $description .= '. Entity objects should be passed using an artifact token (e.g. {{entity:*}}) provided by a previous load/search/lookup tool call.';
        }
        elseif ($type === self::TYPE_OUTPUT) {
          $description = rtrim($description, '.');
          $description .= '. The entity output will be an artifact token (e.g. {{entity:*}}) that can be used by subsequent tool calls.';
        }
        $definitions[$name] = new ContextDefinition(
          data_type: 'string',
          label: $definition->getLabel(),
          required: $definition->isRequired(),
          multiple: $definition->isMultiple(),
          description: $description,
          default_value: $definition->getDefaultValue(),
          constraints: $definition->getConstraints(),
        );
      }
      if ($definition instanceof MapContextDefinition) {
        $property_definitions = $this->prepareContextDefinitions($definition->getPropertyDefinitions(), $type);
        $definitions[$name]->setPropertyDefinitions($property_definitions);
      }
    }
    return $definitions;
  }

  /**
   * {@inheritDoc}
   */
  public function executeTool(string $toolId, mixed $arguments): array {
    $plugin_id = str_replace('___', ':', $toolId);
    $tool = $this->toolManager->createInstance($plugin_id);
    try {
      foreach ($tool->getInputDefinitions() as $name => $definition) {
        if (isset($arguments[$name])) {
          $arguments[$name] = $this->upcastArgument($arguments[$name], $definition);
          $tool->setInputValue($name, $arguments[$name]);
        }
      }
      if ($tool->access()) {
        $tool->execute();
      }
      else {
        return [
          'content' => [
            [
              'type' => 'text',
              'text' => 'Tool plugin access denied.',
            ],
          ],
        ];
      }
    }
    catch (\Exception $e) {
      $message = 'Tool plugin execution failed: ' . $e->getMessage();
      if ($tool instanceof InputDefinitionRefinerInterface) {
        // Decide if we only show the schema for the missing/invalid inputs.
        $input_schema = $this->getContextDefinitionSchema($tool->getInputDefinitions(), self::TYPE_INPUT);
        $message .= "\nThe tool input schema should match: " . json_encode($input_schema);
      }
      return [
        'content' => [
          [
            'type' => 'text',
            'text' => $message,
          ],
        ],
        'isError' => TRUE,
      ];
    }
    $result = $tool->getResult();
    $output = [
      'content' => [
        [
          'type' => 'text',
          'text' => (string) $tool->getResultMessage(),
        ],
      ],
    ];
    if (!$result->isSuccess()) {
      $output['content'][0]['isError'] = TRUE;
    }
    if ($result->isSuccess() && $output_data = $tool->getOutputValues()) {
      $output_data = $this->downcastEntityValues($output_data);
      $output['content'][0]['text'] = rtrim($output['content'][0]['text'], '.');
      // For backwards compatability, serialize output and add to text response.
      $output['content'][0]['text'] .= '. Output: ' . json_encode($output_data);
      $output['structuredContent'] = $output_data;
    }
    return $output;
  }

  /**
   * Upcasts a value according to the input definition.
   *
   * This is a simple upcaster to handle common MCP data issues.
   *
   * @param mixed $argument
   *   The argument to potentially upcast.
   * @param \Drupal\tool\TypedData\InputDefinitionInterface $definition
   *   The associated input definition.
   *
   * @throws \Exception
   */
  protected function upcastArgument(mixed $argument, mixed $definition): mixed {
    if ($definition instanceof InputDefinitionInterface && ($definition->isMultiple() || $definition->getDataType() == 'list'|| $definition instanceof ListInputDefinition)) {
      if (!is_array($argument) && !empty($argument)) {
        $argument = [$argument];
      }

      if ($definition->isMultiple() || $definition instanceof ListInputDefinition) {
        foreach ($argument as $key => $item) {
          $argument[$key] = $this->upcastArgument($item, $definition->getDataDefinition()->getItemDefinition());
        }
      }
      else {
        // If type is a 'list' with no additional definition, leave as is.
      }
    }
    elseif ($definition instanceof MapInputDefinition) {
      foreach ($definition->getPropertyDefinitions() as $property_name => $property_definition) {
        if (isset($argument[$property_name])) {
          $argument[$property_name] = $this->upcastArgument($argument[$property_name], $property_definition);
        }
      }
    }
    else {
      $argument = $this->replaceHandleTokenWithValue($argument);
      // Fix booleans.
      if ($definition->getDataType() == 'boolean') {
        $argument = filter_var($argument, FILTER_VALIDATE_BOOLEAN);
      }
    }
    return $argument;
  }

  /**
   * Loop through values and turn entities into handle tokens.
   *
   * @param array $values
   *   The values to process.
   *
   * @return array
   *   The processed values.
   */
  protected function downcastEntityValues(array $values): array {
    foreach ($values as $key => $value) {
      if (is_array($value)) {
        $values[$key] = $this->downcastEntityValues($value);
        continue;
      }
      if ($value instanceof ContentEntityInterface) {
        $values[$key] = $this->createHandleTokenForEntity($value);
      }
    }
    return $values;
  }

  /**
   * Creates a handle and stores the entity in temporary storage.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to handle and store.
   *
   * @return string
   *   The entity handle.
   */
  protected function createHandleTokenForEntity(ContentEntityInterface $entity): string {
    // @todo Move to unified artifact solution.
    $tempstore = $this->tempStore->get('ai_tool_artifacts');
    $output = '';
    // Get the entity's langcode for the artifact.
    if (!isset($entity->ai_hash)) {
      $hash = substr(md5(serialize($entity)), 0, 6);
      $entity->ai_hash = $hash;
    }
    if ($entity->isNew()) {
      $artifact_key = "{{entity:{$entity->ai_hash}}}";
    }
    else {
      $artifact_key = "{{entity:{$entity->ai_hash}}}";
    }
    $output .= "Entity object handle token: {$artifact_key}. Entity metadata: "
      . json_encode($this->getEntityMetadata($entity));
    $artifact_key = str_replace('{{', 'artifact__', $artifact_key);
    $artifact_key = str_replace('}}', '', $artifact_key);
    $tempstore->delete($artifact_key);
    $tempstore->set($artifact_key, $entity);
    return $output;
  }

  /**
   * Gets entity metadata for storage with artifact.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return array
   *   The entity metadata.
   */
  protected function getEntityMetadata(ContentEntityInterface $entity): array {
    return [
      'entity_type' => $entity->getEntityTypeId(),
      'bundle' => $entity->bundle(),
      'id' => $entity->isNew() ? 'new' : $entity->id(),
      'langcode' => $entity->language()->getId(),
      'revision_id' => $entity instanceof RevisionableInterface || $entity->isNew() ? NULL : $entity->getRevisionId(),
    ];
  }

  /**
   * Replaces valid handle with value.
   *
   * @param mixed $value
   *   The evaluated value.
   *
   * @return mixed
   *   The original value.
   */
  protected function replaceHandleTokenWithValue(mixed $value): mixed {
    if (is_string($value) && str_starts_with($value, '{{') && str_ends_with($value, '}}')) {
      if (preg_match('/{{(.*?)}}/', $value, $matches)) {
        $artifact_id = trim($matches[1]);

        $tempstore = $this->tempStore->get('ai_tool_artifacts');
        // Load the artifact from the temp store.
        $value = $tempstore->get('artifact__' . $artifact_id);
      }
    }
    return $value;
  }

}
