<?php

declare(strict_types=1);

namespace Drupal\mcp\Plugin\McpJsonRpc;

use Drupal\jsonrpc\Object\ParameterBag;
use Drupal\mcp\Plugin\McpJsonRpcMethodBase;
use Drupal\mcp\ServerFeatures\Tool;

/**
 * Lists available tools from the server.
 *
 * @JsonRpcMethod(
 *   id = "tools/list",
 *   usage = @Translation("List available tools."),
 *   params = {
 *     "cursor" = @JsonRpcParameterDefinition(
 *       schema={"type"="string"},
 *       required=false,
 *       description=@Translation("An opaque token representing the current
 *       pagination position.")
 *     )
 *   }
 * )
 */
class ToolsList extends McpJsonRpcMethodBase {

  /**
   * {@inheritdoc}
   */
  public function execute(ParameterBag $params) {
    $tools = [];
    foreach ($this->mcpPluginManager->getAvailablePlugins() as $instance) {
      // Skip plugins that user doesn't have access to.
      if (!$instance->hasAccess()->isAllowed()) {
        continue;
      }

      $instanceTools = $instance->getToolsWithCustomization();
      $availableTools = [];

      // Filter tools based on access and enabled status.
      foreach ($instanceTools as $tool) {
        // Check if tool is enabled and user has access.
        if ($instance->isToolEnabled($tool->name)
          && $instance->hasToolAccess(
            $tool->name
          )->isAllowed()
        ) {
          $availableTools[] = $tool;
        }
      }

      $prefixizedTools = array_map(
        function ($tool) use ($instance) {
          $toolData = new Tool(
            name: $instance->generateToolId(
              $instance->getPluginId(), $tool->name
            ),
            description: $tool->description,
            inputSchema: $tool->inputSchema,
            title: $tool->title ?? NULL,
            outputSchema: $tool->outputSchema ?? NULL,
            annotations: $tool->annotations ?? NULL,
          );

          return $toolData->jsonSerialize();
        },
        $availableTools
      );
      $tools = array_merge($tools, $prefixizedTools);
    }

    return [
      'tools' => $tools,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function outputSchema() {
    return [
      'type'       => 'object',
      'required'   => ['tools'],
      'properties' => [
        'tools'      => [
          'type'  => 'array',
          'items' => [
            'type'       => 'object',
            'required'   => ['name', 'inputSchema'],
            'properties' => [
              'name'         => [
                'type'        => 'string',
                'description' => 'The name of the tool',
              ],
              'description'  => [
                'type'        => 'string',
                'description' => 'A human-readable description of the tool',
              ],
              'inputSchema'  => [
                'type'       => 'object',
                'required'   => ['type'],
                'properties' => [
                  'type' => ['const' => 'object'],
                ],
              ],
              'title'        => [
                'type'        => 'string',
                'description' => 'Optional human-readable display name for the tool',
              ],
              'outputSchema' => [
                'type'        => 'object',
                'description' => 'Optional JSON Schema defining the expected output structure',
              ],
              'annotations'  => [
                'type'        => 'object',
                'description' => 'Optional additional tool information (all properties are HINTS)',
                'properties'  => [
                  'title'           => [
                    'type'        => 'string',
                    'description' => 'A human-readable title for the tool',
                  ],
                  'readOnlyHint'    => [
                    'type'        => 'boolean',
                    'description' => 'If true, the tool does not modify its environment',
                  ],
                  'idempotentHint'  => [
                    'type'        => 'boolean',
                    'description' => 'If true, repeated calls have no additional effect',
                  ],
                  'destructiveHint' => [
                    'type'        => 'boolean',
                    'description' => 'If true, may perform destructive updates',
                  ],
                  'openWorldHint'   => [
                    'type'        => 'boolean',
                    'description' => 'If true, may interact with external entities',
                  ],
                ],
              ],
            ],
          ],
        ],
        'nextCursor' => [
          'type'        => 'string',
          'description' => 'Token for the next page of results',
        ],
      ],
    ];
  }

}
