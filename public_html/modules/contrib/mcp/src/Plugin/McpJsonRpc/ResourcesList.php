<?php

declare(strict_types=1);

namespace Drupal\mcp\Plugin\McpJsonRpc;

use Drupal\jsonrpc\Object\ParameterBag;
use Drupal\mcp\Plugin\McpJsonRpcMethodBase;
use Drupal\mcp\ServerFeatures\Resource;

/**
 * Lists available resources from the server.
 *
 * @JsonRpcMethod(
 *   id = "resources/list",
 *   usage = @Translation("List available resources."),
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
class ResourcesList extends McpJsonRpcMethodBase {

  /**
   * {@inheritdoc}
   */
  public function execute(ParameterBag $params) {
    $resources = [];
    foreach ($this->mcpPluginManager->getAvailablePlugins() as $instance) {
      $instanceResources = $instance->getResources();
      $prefixizedResources = array_map(
        fn($resource) => new Resource(
          uri: $instance->getPluginId() . '://' . $resource->uri,
          name: $resource->name,
          description: $resource->description,
          mimeType: $resource->mimeType,
          text: $resource->text,
        ),
        $instanceResources
      );

      $resources = array_merge($resources, $prefixizedResources);
    }

    return [
      'resources' => $resources,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function outputSchema() {
    return [
      'type'       => 'object',
      'required'   => ['resources'],
      'properties' => [
        'resources'  => [
          'type'  => 'array',
          'items' => [
            'type'       => 'object',
            'required'   => ['name', 'uri'],
            'properties' => [
              'name'        => [
                'type'        => 'string',
                'description' => 'A human-readable name for this resource',
              ],
              'uri'         => [
                'type'        => 'string',
                'format'      => 'uri',
                'description' => 'The URI of this resource',
              ],
              'description' => [
                'type'        => 'string',
                'description' => 'A description of what this resource represents',
              ],
              'mimeType'    => [
                'type'        => 'string',
                'description' => 'The MIME type of this resource, if known',
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
