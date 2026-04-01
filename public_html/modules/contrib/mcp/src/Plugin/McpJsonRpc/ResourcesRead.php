<?php

declare(strict_types=1);

namespace Drupal\mcp\Plugin\McpJsonRpc;

use Drupal\jsonrpc\Exception\JsonRpcException;
use Drupal\jsonrpc\Object\ParameterBag;
use Drupal\mcp\Plugin\McpJsonRpcMethodBase;
use Drupal\mcp\ServerFeatures\Resource;

/**
 * Reads a specific resource.
 *
 * @JsonRpcMethod(
 *   id = "resources/read",
 *   usage = @Translation("Read a specific resource."),
 *   params = {
 *     "uri" = @JsonRpcParameterDefinition(
 *       schema={"type"="string", "format"="uri"},
 *       required=true,
 *       description=@Translation("The URI of the resource to read.")
 *     )
 *   }
 * )
 */
class ResourcesRead extends McpJsonRpcMethodBase {

  /**
   * {@inheritdoc}
   */
  public function execute(ParameterBag $params) {
    $resourceUri = $params->get('uri');
    [$pluginId, $resourceId] = explode('://', $resourceUri);
    $instance = $this->mcpPluginManager->createInstance($pluginId);

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
        new \InvalidArgumentException('Access denied to plugin: ' . $pluginId),
      );
    }

    $resources = $instance->readResource($resourceId);
    if (empty($resources)) {
      return [
        'contents' => [],
      ];
    }

    $prefixizedResources = [];
    foreach ($resources as $resource) {
      if (!$resource instanceof Resource) {
        continue;
      }

      $prefixizedResources[] = new Resource(
        uri: $pluginId . '://' . $resource->uri,
        name: $resource->name,
        description: $resource->description,
        mimeType: $resource->mimeType,
        text: $resource->text,
      );
    }

    return [
      'contents' => $prefixizedResources,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function outputSchema() {
    return [
      'type'       => 'object',
      'required'   => ['contents'],
      'properties' => [
        'contents' => [
          'type'  => 'array',
          'items' => [
            'oneOf' => [
              [
                'type'       => 'object',
                'required'   => ['uri', 'text'],
                'properties' => [
                  'uri'      => [
                    'type'   => 'string',
                    'format' => 'uri',
                  ],
                  'text'     => [
                    'type' => 'string',
                  ],
                  'mimeType' => [
                    'type' => 'string',
                  ],
                ],
              ],
              [
                'type'       => 'object',
                'required'   => ['uri', 'blob'],
                'properties' => [
                  'uri'      => [
                    'type'   => 'string',
                    'format' => 'uri',
                  ],
                  'blob'     => [
                    'type'   => 'string',
                    'format' => 'byte',
                  ],
                  'mimeType' => [
                    'type' => 'string',
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ];
  }

}
