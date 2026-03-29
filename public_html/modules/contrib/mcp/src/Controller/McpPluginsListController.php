<?php

declare(strict_types=1);

namespace Drupal\mcp\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\mcp\Plugin\McpPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for MCP plugin testing and administration.
 */
class McpPluginsListController extends ControllerBase {

  /**
   * The MCP plugin manager.
   *
   * @var \Drupal\mcp\Plugin\McpPluginManager
   */
  protected McpPluginManager $pluginManager;

  /**
   * Constructs a new McpTestController object.
   *
   * @param \Drupal\mcp\Plugin\McpPluginManager $plugin_manager
   *   The MCP plugin manager.
   */
  public function __construct(McpPluginManager $plugin_manager) {
    $this->pluginManager = $plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin.manager.mcp')
    );
  }

  /**
   * Displays a list of MCP plugins.
   *
   * @return array
   *   A render array containing the plugin list.
   */
  public function list(): array {
    $header = [
      $this->t('Name'),
      $this->t('Description'),
      $this->t('Status'),
      $this->t('Operations'),
    ];

    $rows = [];

    try {
      $definitions = $this->pluginManager->getDefinitions();

      foreach ($definitions as $plugin_id => $definition) {
        $plugin = $this->pluginManager->createInstance($plugin_id);

        // Check if plugin meets requirements.
        $meets_requirements = $plugin->checkRequirements();
        $requirements_description = $plugin->getRequirementsDescription();

        // Build status cell.
        if ($meets_requirements) {
          if ($plugin->isEnabled()) {
            $status = [
              '#markup' => '<span class="color-success">' . $this->t('Enabled') . '</span>',
            ];
          }
          else {
            $status = [
              '#markup' => '<span class="color-warning">' . $this->t('Disabled') . '</span>',
            ];
          }
        }
        else {
          $status = [
            '#markup' => '<span class="color-error">' . $this->t('Requirements not met') . '</span>',
          ];
          if (!empty($requirements_description)) {
            $status['#markup'] .= '<br><small>' . $requirements_description . '</small>';
          }
        }

        // Build operations based on requirements.
        $operations = [];
        if ($meets_requirements) {
          $operations['configure'] = [
            'title' => $this->t('Configure'),
            'url' => Url::fromRoute('mcp.plugin.settings', ['plugin' => $plugin_id]),
          ];
        }

        $rows[] = [
          $definition['name'] ?? $plugin_id,
          $definition['description'] ?? '',
          ['data' => $status],
          [
            'data' => [
              '#type' => 'operations',
              '#links' => $operations,
            ],
          ],
        ];
      }
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Error loading MCP plugins: @message', [
        '@message' => $e->getMessage(),
      ]));
    }

    // Add CSS for status colors.
    $build = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No MCP plugins found.'),
      '#attached' => [
        'library' => ['mcp/admin-styles'],
      ],
    ];

    return $build;
  }

}
