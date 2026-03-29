<?php

declare(strict_types=1);

namespace Drupal\mcp\Plugin\McpJsonRpc;

use Drupal\jsonrpc\Exception\JsonRpcException;
use Drupal\jsonrpc\Object\ParameterBag;
use Drupal\mcp\Plugin\McpJsonRpcMethodBase;
use Drupal\mcp\ServerFeatures\Resource;

/**
 * Executes a tool call.
 *
 * @JsonRpcMethod(
 *   id = "tools/call",
 *   usage = @Translation("Call a tool with specified parameters."),
 *   params = {
 *     "name" = @JsonRpcParameterDefinition(
 *       schema={"type"="string"},
 *       required=true,
 *       description=@Translation("The name of the tool to call.")
 *     ),
 *     "arguments" = @JsonRpcParameterDefinition(
 *       schema={"type"="object"},
 *       required=false,
 *       description=@Translation("Arguments to pass to the tool.")
 *     )
 *   }
 * )
 */
class ToolsCall extends McpJsonRpcMethodBase {

  /**
   * {@inheritdoc}
   */
  public function execute(ParameterBag $params) {
    $toolId = $params->get('name');
    $arguments = $params->get('arguments');

    [$definitionId, $toolPart] = explode('_', $toolId, 2);
    $instance = $this->mcpPluginManager->createInstance($definitionId);

    if (!$instance->checkRequirements() || !$instance->isEnabled()) {
      throw JsonRpcException::fromPrevious(
        new \InvalidArgumentException(
          'Plugin not enabled or requirements not met.'
        )
      );
    }

    // Check if user has access to this specific plugin.
    if (!$instance->hasAccess()->isAllowed()) {
      throw JsonRpcException::fromPrevious(
        new \InvalidArgumentException(
          'Access denied to plugin: ' . $definitionId
        ),
      );
    }

    $actualToolName = NULL;
    foreach ($instance->getToolsWithCustomization() as $tool) {
      $sanitizedName = $instance->sanitizeToolName($tool->name);

      if ($sanitizedName === $toolPart ||
          md5($tool->name) === $toolPart ||
          $instance->generateToolId($definitionId, $tool->name) === $toolId) {
        $actualToolName = $tool->name;
        break;
      }
    }

    if ($actualToolName === NULL) {
      throw JsonRpcException::fromPrevious(
        new \InvalidArgumentException(
          'Tool not found: ' . $toolId
        )
      );
    }

    // Check tool-level access.
    if (!$instance->isToolEnabled($actualToolName)) {
      throw JsonRpcException::fromPrevious(
        new \InvalidArgumentException(
          'Tool is disabled: ' . $actualToolName
        )
      );
    }

    $toolAccess = $instance->hasToolAccess($actualToolName);
    if (!$toolAccess->isAllowed()) {
      throw JsonRpcException::fromPrevious(
        new \InvalidArgumentException(
          'Access denied to tool: ' . $actualToolName
        ),
      );
    }

    $toolResult = $instance->executeTool($instance->sanitizeToolName($actualToolName), $arguments);

    if (empty($toolResult)) {
      return ['content' => []];
    }
    // Ensure backward compatibility.
    elseif (array_is_list($toolResult)) {
      $toolResult = ['content' => $toolResult];
    }

    $returnResult = [];
    if (isset($toolResult['content'])) {
      foreach ($toolResult['content'] as $result) {
        $type = $result['type'];
        if (!empty($type) && !in_array($type, ['text', 'image', 'resource'])) {
          throw JsonRpcException::fromPrevious(
            new \InvalidArgumentException('Invalid result type: ' . $type)
          );
        }
        if ($type === 'resource' && $result['resource'] instanceof Resource) {
          $resource = $result['resource'];
          $result = [
            'type' => 'resource',
            'resource' => new Resource(
              uri: $instance->getPluginId() . '://' . $resource->uri,
              name: $resource->name,
              description: $resource->description,
              mimeType: $resource->mimeType,
              text: $resource->text,
            ),
          ];
        }
        $returnResult['content'][] = $result;
      }
    }
    if (isset($toolResult['structuredContent'])) {
      $returnResult['structuredContent'] = $toolResult['structuredContent'];
    }
    if (isset($toolResult['isError'])) {
      $returnResult['isError'] = $toolResult['isError'];
    }
    return $returnResult;
  }

  /**
   * {@inheritdoc}
   */
  public static function outputSchema(): array {
    return [
      'type'       => 'object',
      'required'   => ['content'],
      'properties' => [
        'content' => [
          'type'  => 'array',
          'items' => [
            'oneOf' => [
              [
                'type'       => 'object',
                'required'   => ['type', 'text'],
                'properties' => [
                  'type' => ['const' => 'text'],
                  'text' => ['type' => 'string'],
                ],
              ],
              [
                'type'       => 'object',
                'required'   => ['type', 'data', 'mimeType'],
                'properties' => [
                  'type'     => ['const' => 'image'],
                  'data'     => ['type' => 'string'],
                  'mimeType' => ['type' => 'string'],
                ],
              ],
              [
                'type'       => 'object',
                'required'   => ['type', 'resource'],
                'properties' => [
                  'type'     => ['const' => 'resource'],
                  'resource' => [
                    'type'  => 'object',
                    'oneOf' => ResourcesRead::outputSchema(
                    )['properties']['contents']['items']['oneOf'],
                  ],
                ],
              ],
            ],
          ],
        ],
        'structuredContent' => [
          'type' => 'object',
        ],
        'isError' => [
          'type'        => 'boolean',
          'description' => 'Whether the tool call ended in an error',
        ],
      ],
    ];
  }

}
