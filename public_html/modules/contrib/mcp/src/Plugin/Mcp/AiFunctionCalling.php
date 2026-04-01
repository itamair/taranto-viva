<?php

namespace Drupal\mcp\Plugin\Mcp;

use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutput;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp\Attribute\Mcp;
use Drupal\mcp\Plugin\McpPluginBase;
use Drupal\mcp\ServerFeatures\Tool;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the mcp.
 */
#[Mcp(
  id: 'aif',
  name: new TranslatableMarkup('AI Function Calling'),
  description: new TranslatableMarkup(
    'Provides AI function calling capabilities through the MCP protocol.'
  ),
)]
class AiFunctionCalling extends McpPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The AI function calls plugin manager.
   *
   * @var ?\Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager
   */
  protected $aiFunctionCalls;

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

    $instance->aiFunctionCalls = $container->get(
      'plugin.manager.ai.function_calls',
      ContainerInterface::NULL_ON_INVALID_REFERENCE
    );

    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function checkRequirements(): bool {
    return $this->aiFunctionCalls !== NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function getRequirementsDescription(): string {
    if (!$this->checkRequirements()) {
      return $this->t('The AI module with function calling support must be installed and configured.');
    }
    return '';
  }

  /**
   * {@inheritDoc}
   */
  public function getTools(): array {
    $functionCallDefinitions = $this->aiFunctionCalls->getDefinitions();
    $tools = [];
    foreach ($functionCallDefinitions as $functionCallDefinition) {
      $instance = $this->aiFunctionCalls->createInstance(
        $functionCallDefinition['id']
      );
      $normalized = $instance->normalize();
      $converted = $normalized->renderFunctionArray();

      $tools[] = new Tool(
        name: $converted['name'],
        description: $converted['description'],
        inputSchema: $converted['parameters'] ?? [
          'type'       => 'object',
          'properties' => new \stdClass(),
        ],
      );
    }

    return $tools;
  }

  /**
   * {@inheritDoc}
   */
  public function executeTool(string $toolId, mixed $arguments): array {
    $definitions = $this->aiFunctionCalls->getDefinitions();
    $instances = [];
    foreach ($definitions as $definition) {
      $instance = $this->aiFunctionCalls->createInstance($definition['id']);
      if (!$instance instanceof ExecutableFunctionCallInterface) {
        continue;
      }
      $functionName = $instance->getFunctionName();
      $sanitizedName = $this->sanitizeToolName($functionName);
      if ($sanitizedName === $toolId || md5($functionName) === $toolId) {
        $instances[] = $instance;
      }
    }

    if (empty($instances)) {
      return [];
    }

    $results = [];
    foreach ($instances as $instance) {
      $input = new ToolsFunctionOutput(
        input: $instance->normalize(),
        tool_id: $instance->getToolsId(),
        arguments: $arguments,
      );
      $input->validate();
      $instance->populateValues($input);
      $instance->execute();

      $results[]
        = [
          "type" => "text",
          "text" => $instance->getReadableOutput(),
        ];
    }

    return $results;
  }

}
