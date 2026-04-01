<?php

declare(strict_types=1);

namespace Drupal\mcp\Plugin\Mcp;

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
  id: 'general',
  name: new TranslatableMarkup('General MCP'),
  description: new TranslatableMarkup(
    'Provides general MCP functionality and basic tools.'
  ),
)]
class General extends McpPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

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

    $instance->configFactory = $container->get('config.factory');
    $instance->moduleHandler = $container->get('module_handler');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getTools(): array {
    return [
      new Tool(
        name: "info",
        description: 'Returns information about the site.',
        inputSchema: [
          'type'       => 'object',
          'properties' => new \stdClass(),
        ],
        title: 'Site Information',
        outputSchema: [
          'type'       => 'object',
          'properties' => [
            'siteName'   => [
              'type'        => 'string',
              'description' => 'The name of the Drupal site',
            ],
            'siteSlogan' => [
              'type'        => 'string',
              'description' => 'The slogan of the Drupal site',
            ],
            'version'    => [
              'type'        => 'string',
              'description' => 'The Drupal core version',
            ],
          ],
          'required'   => ['siteName', 'siteSlogan', 'version'],
        ],
      ),
      new Tool(
        name: "status",
        description: 'Returns information about the modules status.',
        inputSchema: [
          'type'       => 'object',
          'properties' => new \stdClass(),
        ]
      ),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function executeTool(string $toolId, mixed $arguments): array {
    if ($toolId === 'info' || $toolId === md5('info')) {
      return [
        [
          "type" => "text",
          "text" => json_encode([
            'siteName'   => $this->configFactory->get('system.site')->get(
              'name'
            ),
            'siteSlogan' => $this->configFactory->get('system.site')->get(
              'slogan'
            ),
            'version'    => \Drupal::VERSION,
          ]),
        ],
      ];
    }

    if ($toolId === 'status' || $toolId === md5('status')) {
      $this->moduleHandler->loadInclude('update', 'inc', 'update.compare');

      // Get available update data.
      $available = update_get_available(TRUE);

      if (empty($available)) {
        return [
          [
            "type" => "text",
            "text" => json_encode([
              'status'  => 'no_data',
              'message' => 'No update information available. Run cron or check manually.',
            ]),
          ],
        ];
      }

      // Calculate project update status.
      $projects = update_calculate_project_data($available);

      // Filter projects that need updates.
      $modules_needing_update = [];
      foreach ($projects as $project_name => $project) {
        // Check if the project needs attention (has updates or issues).
        // Status values from UpdateManagerInterface:
        // NOT_SECURE = 1,
        // REVOKED = 2,
        // NOT_SUPPORTED = 3,
        // NOT_CURRENT = 4,
        // CURRENT = 5.
        if (isset($project['status']) && $project['status'] < 5) {
          $status_label = match ($project['status']) {
            1 => 'Security update required',
            2 => 'Revoked',
            3 => 'Not supported',
            4 => 'Update available',
            default => 'Unknown',
          };

          $modules_needing_update[] = [
            'name'                => $project['title'] ?? $project_name,
            'project'             => $project_name,
            'existing_version'    => $project['existing_version'] ?? 'Unknown',
            'recommended_version' => $project['recommended'] ?? 'Unknown',
            'status'              => $status_label,
            'status_code'         => $project['status'],
            'link'                => $project['link'] ?? NULL,
          ];
        }
      }

      return [
        [
          "type" => "text",
          "text" => json_encode([
            'total_projects'         => count($projects),
            'modules_needing_update' => $modules_needing_update,
            'count'                  => count($modules_needing_update),
          ]),
        ],
      ];
    }

    throw new \InvalidArgumentException('Tool not found');
  }

}
