<?php

namespace Drupal\mcp\Plugin\Mcp;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp\Attribute\Mcp;
use Drupal\mcp\Plugin\McpPluginBase;
use Drupal\mcp\ServerFeatures\Tool;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Plugin implementation of the drush command caller.
 */
#[Mcp(
  id: 'drush',
  name: new TranslatableMarkup('Drush Commands'),
  description: new TranslatableMarkup(
    'A plugin that allows you to call Drush commands. This is only for development purposes. Use with caution.'
  ),
)]
class DrushCaller extends McpPluginBase implements ContainerFactoryPluginInterface {

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

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $config = parent::defaultConfiguration();
    $config['enabled'] = FALSE;

    $tools = $this->getTools();
    foreach ($tools as $tool) {
      $config['tools'][$tool->name] = [
        'enabled' => FALSE,
        'roles' => [],
        'description' => '',
      ];
    }

    return $config;
  }

  /**
   * {@inheritDoc}
   */
  public function checkRequirements(): bool {
    try {
      // Check if drush is available and can execute commands.
      $process = new Process(['drush', 'version']);
      $process->setTimeout(10);
      $process->run();

      if (!$process->isSuccessful()) {
        return FALSE;
      }

      return TRUE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getRequirementsDescription(): string {
    if (!$this->checkRequirements()) {
      return $this->t('Drush must be installed and accessible from the command line.');
    }
    return '';
  }

  /**
   * Get the list of Drush commands.
   */
  private function getDrushCommands() {
    $process = new Process(['drush', 'list', '--format=json']);
    $process->setTimeout(60);
    $process->mustRun();
    $output = $process->getOutput();

    return json_decode($output, TRUE);
  }

  /**
   * Check if a command is allowed based on tool configuration.
   */
  private function isCommandAllowed(string $command): bool {
    $config = $this->getConfiguration();
    $tools_config = $config['tools'] ?? [];

    if (empty($tools_config)) {
      return FALSE;
    }

    if (isset($tools_config[$command])) {
      return !empty($tools_config[$command]['enabled']);
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getTools(): array {
    $tools = [];
    $parsed_output = $this->getDrushCommands();
    if (isset($parsed_output['commands'])
      && is_array(
        $parsed_output['commands']
      )
    ) {
      foreach ($parsed_output['commands'] as $command) {
        if (isset($command['hidden']) && $command['hidden']) {
          continue;
        }

        $usage = implode(' ', $command['usage']);
        $description
          = "Description: $command[description]; Help: $command[help]; Usage: $usage";
        $schema = $this->createJsonSchema($command);
        $tool = new Tool(
          name: $command['name'],
          description: $description,
          inputSchema: empty($schema['properties']) ? [
            'type'       => 'object',
            'properties' => new \stdClass(),
          ] : $schema,
        );

        $tools[] = $tool;
      }
    }

    return $tools;
  }

  /**
   * Creates a JSON schema for a Drush command.
   */
  protected function createJsonSchema(array $command) {
    $schema = [
      'type'       => 'object',
      'properties' => [],
      'required'   => [],
    ];

    if (isset($command['definition']['arguments'])
      && is_array(
        $command['definition']['arguments']
      )
    ) {
      foreach ($command['definition']['arguments'] as $arg_name => $arg_details) {
        $schema['properties'][$arg_name] = [
          'type'        => 'string',
          'title'       => $arg_name,
          'description' => $arg_details['description'] ?? "Argument: $arg_name",
        ];
        if (isset($arg_details['is_required']) && $arg_details['is_required']) {
          $schema['required'][] = $arg_name;
        }
      }
    }

    if (isset($command['definition']['options'])
      && is_array(
        $command['definition']['options']
      )
    ) {
      foreach ($command['definition']['options'] as $opt_name => $opt_details) {
        if (in_array($opt_name, [
          'help',
          'silent',
          'quiet',
          'verbose',
          'version',
          'ansi',
          'no-ansi',
          'no-interaction',
          'yes',
          'no',
          'root',
          'uri',
          'simulate',
          'define',
          'xdebug',
        ])
        ) {
          continue;
        }

        $type = 'string';
        if (isset($opt_details['accept_value'])
          && !$opt_details['accept_value']
        ) {
          $type = 'boolean';
        }

        $schema['properties'][$opt_name] = [
          'type'        => $type,
          'title'       => $opt_name,
          'description' => $opt_details['description'] ?? "Option: --$opt_name",
        ];

        if (isset($opt_details['is_required']) && $opt_details['is_required']) {
          $schema['required'][] = $opt_name;
        }
      }
    }

    return $schema;
  }

  /**
   * {@inheritDoc}
   */
  public function executeTool(string $toolId, mixed $arguments): array {
    $commnds = $this->getDrushCommands();
    $commandName = NULL;
    foreach ($commnds['commands'] as $command) {
      $sanitizedName = $this->sanitizeToolName($command['name']);
      if ($sanitizedName === $toolId || md5($command['name']) === $toolId) {
        $commandName = $command['name'];
        break;
      }
    }

    if (!$commandName) {
      $commandName = $toolId;
    }

    // Validate command is allowed.
    if (!$this->isCommandAllowed($commandName)) {
      return [
        [
          'type' => 'text',
          'text' => sprintf(
            'Error: Command "%s" is not allowed. Please enable it in the MCP Dev Tools configuration.',
            $commandName
          ),
        ],
      ];
    }

    $cmd = ['drush', escapeshellarg($commandName)];
    $process = new Process(['drush', 'help', escapeshellarg($commandName), '--format=json']);
    try {
      $process->run();
      $help = json_decode($process->getOutput(), TRUE);

      if (isset($help['arguments']) && is_array($help['arguments'])) {
        foreach ($help['arguments'] as $arg_name => $arg_details) {
          if (isset($arguments[$arg_name]) && is_scalar($arguments[$arg_name])) {
            $cmd[] = escapeshellarg($arguments[$arg_name]);
            unset($arguments[$arg_name]);
          }
        }
      }

      foreach ($arguments as $key => $value) {
        if (is_bool($value)) {
          if ($value) {
            $cmd[] = "--" . escapeshellarg($key);
          }
        }
        elseif (!empty($value) && is_scalar($value)) {
          $cmd[] = "--" . escapeshellarg($key) . "=" . escapeshellarg($value);
        }
      }
    }
    catch (\Exception $e) {
      foreach ($arguments as $key => $value) {
        if (is_bool($value)) {
          if ($value) {
            $cmd[] = "--" . escapeshellarg($key);
          }
        }
        elseif (is_scalar($value)) {
          $cmd[] = escapeshellarg($value);
        }
      }
    }

    $cmd[] = '--yes';
    $cmd[] = '--no-interaction';

    $process = new Process($cmd);
    $process->setTimeout(3600);

    try {
      $process->mustRun();

      return [
        [
          'type' => 'text',
          'text' => $process->getOutput() ?? 'Command run successfully.',
        ],
      ];
    }
    catch (ProcessFailedException $exception) {
      throw new \Exception($exception->getMessage());
    }
  }

}
