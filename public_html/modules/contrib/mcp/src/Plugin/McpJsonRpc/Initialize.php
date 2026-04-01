<?php

declare(strict_types=1);

namespace Drupal\mcp\Plugin\McpJsonRpc;

use Drupal\jsonrpc\Object\ParameterBag;
use Drupal\jsonrpc\Plugin\JsonRpcMethodBase;

/**
 * Handles the initialization request from a client.
 *
 * @JsonRpcMethod(
 *   id = "initialize",
 *   usage = @Translation("Initialize an MCP client-server connection."),
 *   params = {
 *     "capabilities" = @JsonRpcParameterDefinition(
 *       schema={
 *         "type"="object",
 *         "properties"={
 *           "experimental"={
 *             "type"="object",
 *             "description"="Experimental, non-standard capabilities that the
 *             client supports"
 *           },
 *           "roots"={
 *             "type"="object",
 *             "properties"={
 *               "listChanged"={
 *                 "type"="boolean",
 *                 "description"="Whether the client supports notifications for
 *                 changes to the roots list"
 *               }
 *             }
 *           },
 *           "sampling"={
 *             "type"="object",
 *             "description"="Present if the client supports sampling from an
 *             LLM"
 *           }
 *         }
 *       },
 *       required=true,
 *       description=@Translation("Capabilities that the client supports.")
 *     ),
 *     "clientInfo" = @JsonRpcParameterDefinition(
 *       schema={
 *         "type"="object",
 *         "properties"={
 *           "name"={"type"="string"},
 *           "version"={"type"="string"}
 *         },
 *         "required"={"name", "version"}
 *       },
 *       required=true,
 *       description=@Translation("Information about the client
 *       implementation.")
 *     ),
 *     "protocolVersion" = @JsonRpcParameterDefinition(
 *       schema={"type"="string"},
 *       required=true,
 *       description=@Translation("The latest version of the Model Context
 *       Protocol that the client supports.")
 *     )
 *   }
 * )
 */
class Initialize extends JsonRpcMethodBase {

  const MCP_PROTOCOL_VERSION = '2025-03-26';

  /**
   * {@inheritdoc}
   */
  public function execute(ParameterBag $params) {
    return (object) [
      'protocolVersion' => self::MCP_PROTOCOL_VERSION,
      'capabilities'    => [
        'resources' => new \stdClass(),
        'tools'     => new \stdClass(),
      ],
      "serverInfo"      => [
        'name'    => 'Drupal MCP Server',
        'version' => '0.0.1',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function outputSchema() {
    return [
      'type'       => 'object',
      'required'   => ['capabilities', 'protocolVersion', 'serverInfo'],
      'properties' => [
        'capabilities'    => [
          'type'       => 'object',
          'properties' => [
            'experimental' => [
              'type'        => 'object',
              'description' => 'Experimental, non-standard capabilities that the server supports',
            ],
            'logging'      => [
              'type'        => 'object',
              'description' => 'Present if the server supports sending log messages to the client',
            ],
            'prompts'      => [
              'type'       => 'object',
              'properties' => [
                'listChanged' => [
                  'type'        => 'boolean',
                  'description' => 'Whether this server supports notifications for changes to the prompt list',
                ],
              ],
            ],
            'resources'    => [
              'type'       => 'object',
              'properties' => [
                'listChanged' => [
                  'type'        => 'boolean',
                  'description' => 'Whether this server supports notifications for changes to the resource list',
                ],
                'subscribe'   => [
                  'type'        => 'boolean',
                  'description' => 'Whether this server supports subscribing to resource updates',
                ],
              ],
            ],
            'tools'        => [
              'type'       => 'object',
              'properties' => [
                'listChanged' => [
                  'type'        => 'boolean',
                  'description' => 'Whether this server supports notifications for changes to the tool list',
                ],
              ],
            ],
          ],
        ],
        'protocolVersion' => [
          'type'        => 'string',
          'description' => 'The version of the Model Context Protocol that the server wants to use',
        ],
        'serverInfo'      => [
          'type'       => 'object',
          'required'   => ['name', 'version'],
          'properties' => [
            'name'    => ['type' => 'string'],
            'version' => ['type' => 'string'],
          ],
        ],
        'instructions'    => [
          'type'        => 'string',
          'description' => 'Instructions describing how to use the server and its features',
        ],
      ],
    ];
  }

}
