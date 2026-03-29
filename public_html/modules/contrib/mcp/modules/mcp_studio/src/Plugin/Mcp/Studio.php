<?php

namespace Drupal\mcp_studio\Plugin\Mcp;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp\Attribute\Mcp;
use Drupal\mcp\Plugin\McpPluginBase;
use Drupal\mcp\ServerFeatures\Tool;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Environment;

/**
 * Provides the Studio MCP plugin.
 */
#[Mcp(
  id: 'studio',
  name: new TranslatableMarkup('MCP Studio'),
  description: new TranslatableMarkup('Creates MCP tools without coding'),
)]
/**
 * Provides the Studio MCP plugin.
 */
class Studio extends McpPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|null
   *   The config factory service.
   */
  protected ConfigFactoryInterface | null $configFactory;

  /**
   * The Twig environment.
   *
   * @var \Twig\Environment|null
   *   The Twig environment service.
   */
  protected Environment | null $twig;

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
    $instance->twig = $container->get('twig');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $config = parent::defaultConfiguration();
    $config['enabled'] = FALSE;
    $config['config']['allowed_commands'] = [];

    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function checkRequirements(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getTools(): array {
    $tools = [];
    $config = $this->configFactory->get('mcp_studio.settings');
    $studio_tools = $config->get('tools') ?? [];

    foreach ($studio_tools as $tool_config) {
      if (!empty($tool_config['name']) && !empty($tool_config['description'])) {
        $inputSchema = [];
        if (!empty($tool_config['input_schema'])) {
          $inputSchema = json_decode($tool_config['input_schema'], TRUE) ?? [];
        }

        $tools[] = new Tool(
          name: $tool_config['name'],
          description: $tool_config['description'],
          inputSchema:  !empty($inputSchema) ? $inputSchema : [
            'type'       => 'object',
            'properties' => new \stdClass(),
          ],
        );
      }
    }

    return $tools;
  }

  /**
   * {@inheritdoc}
   */
  public function executeTool(string $toolId, mixed $arguments): array {
    $config = $this->configFactory->get('mcp_studio.settings');
    $studio_tools = $config->get('tools') ?? [];

    foreach ($studio_tools as $tool_config) {
      $sanitized_name = $this->sanitizeToolName($tool_config['name']);
      if ($sanitized_name === $toolId) {
        $output = $tool_config['output'] ?? '';

        // Check if the mode is Twig.
        if (!empty($tool_config['output_mode']) && $tool_config['output_mode'] === 'twig') {
          try {
            // Create a Twig template from the output string.
            $template = $this->twig->createTemplate($output);

            // Convert arguments to array if it's an object.
            $context = is_array($arguments) ? $arguments : (array) $arguments;

            // Render the template with the arguments as context.
            $output = $template->render($context);
          }
          catch (\Exception $e) {
            throw new \RuntimeException("Failed to render Twig template: " . $e->getMessage());
          }
        }

        return [
          [
            'type' => 'text',
            'text' => $output,
          ],
        ];
      }
    }

    throw new \RuntimeException("Tool '$toolId' not found in Studio configuration.");
  }

}
