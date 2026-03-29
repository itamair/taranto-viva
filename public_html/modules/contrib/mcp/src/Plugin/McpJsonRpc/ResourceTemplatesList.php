<?php

declare(strict_types=1);

namespace Drupal\mcp\Plugin\McpJsonRpc;

use Drupal\jsonrpc\Object\ParameterBag;
use Drupal\mcp\Plugin\McpJsonRpcMethodBase;
use Drupal\mcp\ServerFeatures\ResourceTemplate;

/**
 * Lists available resource templates from the server.
 *
 * @JsonRpcMethod(
 *   id = "resources/templates/list",
 *   usage = @Translation("List available resource templates."),
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
class ResourceTemplatesList extends McpJsonRpcMethodBase {

  /**
   * {@inheritdoc}
   */
  public function execute(ParameterBag $params) {
    $resourceTemplates = [];
    foreach ($this->mcpPluginManager->getAvailablePlugins() as $instance) {
      $instanceResourceTemplates = $instance->getResourceTemplates();
      $prefixizedResourceTemplates = array_map(
        fn($resourceTemplate) => new ResourceTemplate(
          uriTemplate: $instance->getPluginId() . '://'
          . $resourceTemplate->uriTemplate,
          name: $resourceTemplate->name,
          description: $resourceTemplate->description,
          mimeType: $resourceTemplate->mimeType,
        ),
        $instanceResourceTemplates
      );

      $resourceTemplates = array_merge(
        $resourceTemplates, $prefixizedResourceTemplates
      );
    }

    return [
      'resourceTemplates' => $resourceTemplates,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function outputSchema() {
    return [
      'type'       => 'object',
      'required'   => ['resourceTemplates'],
      'properties' => [
        'resourceTemplates' => [
          'type'  => 'array',
          'items' => [
            'type'       => 'object',
            'required'   => ['name', 'uriTemplate'],
            'properties' => [
              'name'        => [
                'type'        => 'string',
                'description' => 'A human-readable name for the type of resource',
              ],
              'uriTemplate' => [
                'type'        => 'string',
                'format'      => 'uri-template',
                'description' => 'A URI template that can be used to construct resource URIs',
              ],
              'description' => [
                'type'        => 'string',
                'description' => 'A description of what this template is for',
              ],
              'mimeType'    => [
                'type'        => 'string',
                'description' => 'The MIME type for all resources that match this template',
              ],
            ],
          ],
        ],
        'nextCursor'        => [
          'type'        => 'string',
          'description' => 'Token for the next page of results',
        ],
      ],
    ];
  }

}
