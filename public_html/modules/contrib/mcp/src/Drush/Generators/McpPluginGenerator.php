<?php

declare(strict_types=1);

namespace Drupal\mcp\Drush\Generators;

use DrupalCodeGenerator\Asset\AssetCollection;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\GeneratorType;
use DrupalCodeGenerator\Utils;
use DrupalCodeGenerator\Validator\RegExp;

/**
 * Generates an MCP plugin.
 */
#[Generator(
  name: 'mcp:plugin',
  description: 'Generates an MCP plugin',
  aliases: ['mcp'],
  templatePath: __DIR__ . '/templates',
  type: GeneratorType::MODULE_COMPONENT,
)]
class McpPluginGenerator extends BaseGenerator {

  /**
   * {@inheritdoc}
   */
  protected function generate(array &$vars, AssetCollection $assets): void {
    $ir = $this->createInterviewer($vars);

    $vars['machine_name'] = $ir->askMachineName();
    $vars['name'] = $ir->askName();

    $plugin_id = $ir->ask(
      'Plugin ID', '{machine_name}',
      new RegExp(
        '/^[a-z0-9-]+$/',
        'The plugin ID must be in lowercase and contain only letters, numbers, and hyphens.'
      )
    );
    $vars['plugin_id'] = $plugin_id;

    $plugin_name = $ir->ask('Plugin name', Utils::machine2human($plugin_id));
    $vars['plugin_name'] = $plugin_name;

    $vars['plugin_description'] = $ir->ask(
      'Plugin description',
      sprintf('Provides %s functionality.', strtolower($plugin_name))
    );

    $vars['class'] = Utils::camelize($plugin_id);

    $vars['services'] = $ir->askServices(FALSE);

    // Ask about tools and resources.
    $vars['has_tools'] = $ir->confirm(
      'Would you like to add tools support?', FALSE
    );
    $vars['has_resources'] = $ir->confirm(
      'Would you like to add resources support?', FALSE
    );

    $assets->addFile('src/Plugin/Mcp/{class}.php', 'mcp-plugin.twig');
  }

}
