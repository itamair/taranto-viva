<?php

declare(strict_types=1);

namespace Drupal\mcp\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp\Plugin\McpInterface;
use Drupal\mcp\Plugin\McpPluginManager;
use Drupal\user\Entity\Role;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Model Context Protocol settings for this site.
 */
class McpPluginSettingsForm extends ConfigFormBase {

  /**
   * The MCP plugin manager.
   *
   * @var \Drupal\mcp\Plugin\McpPluginManager
   */
  protected McpPluginManager $pluginManager;

  /**
   * Constructs a new McpPluginSettingsForm.
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
   * The _title_callback for the mcp.plugin.settings route.
   *
   * @param \Drupal\mcp\Plugin\McpInterface $plugin
   *   The current MCP plugin.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The page title.
   */
  public function formTitle(McpInterface $plugin): TranslatableMarkup {
    $plugin_definitions = $plugin->getPluginDefinition();

    return $this->t(
      'Configure @plugin',
      ['@plugin' => $plugin_definitions['name']]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mcp_mcp_plugin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['mcp.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    ?McpInterface $plugin = NULL,
  ): array {
    $plugin_config = $plugin->getConfiguration();
    $form = parent::buildForm($form, $form_state);

    $this->buildPluginSettingsSection($form, $plugin_config);
    $this->buildCustomConfigSection($form, $plugin, $form_state);
    $this->buildToolsSection($form, $plugin, $plugin_config);

    return $form;
  }

  /**
   * Build plugin settings section.
   */
  protected function buildPluginSettingsSection(
    array &$form,
    array $plugin_config,
  ): void {
    $form['plugin_settings'] = [
      '#type'  => 'fieldset',
      '#title' => $this->t('Plugin Settings'),
      '#tree'  => TRUE,
    ];

    $form['plugin_settings']['enabled'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Enable plugin'),
      '#description'   => $this->t('Enable or disable this plugin globally.'),
      '#default_value' => $plugin_config['enabled'] ?? TRUE,
    ];

    $form['plugin_settings']['roles'] = [
      '#type'          => 'checkboxes',
      '#title'         => $this->t('Allowed roles'),
      '#description'   => $this->t(
        'Select which roles can access this plugin. If none selected, all authenticated users can access.'
      ),
      '#options'       => $this->getRoleOptions(),
      '#default_value' => array_combine(
        $plugin_config['roles'] ?? ['authenticated'],
        $plugin_config['roles'] ?? ['authenticated']
      ),
      '#states'        => [
        'visible' => [
          ':input[name="plugin_settings[enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];
  }

  /**
   * Build custom configuration section.
   */
  protected function buildCustomConfigSection(
    array &$form,
    McpInterface $plugin,
    FormStateInterface $form_state,
  ): void {
    $custom_config_form = $plugin->buildConfigurationForm([], $form_state);
    if (!empty($custom_config_form)) {
      $form['plugin_settings']['config'] = [
        '#type'   => 'fieldset',
        '#title'  => $this->t('Additional Configuration'),
        '#tree'   => TRUE,
        '#states' => [
          'visible' => [
            ':input[name="plugin_settings[enabled]"]' => ['checked' => TRUE],
          ],
        ],
      ] + $custom_config_form;
    }
  }

  /**
   * Build tools configuration section.
   */
  protected function buildToolsSection(
    array &$form,
    McpInterface $plugin,
    array $plugin_config,
  ): void {
    $tools = $plugin->getTools();
    if (empty($tools)) {
      return;
    }

    // Attach the JavaScript library for search functionality.
    $form['#attached']['library'][] = 'mcp/mcp.admin';

    $form['tools_settings'] = [
      '#type'   => 'container',
      '#tree'   => TRUE,
      '#prefix' => '<h3>' . $this->t('Tool Settings') . '</h3>' .
      '<div class="description">' . $this->t(
          'Configure individual tools provided by this plugin.'
      ) . '</div>',
    ];

    // Add search field for tools.
    $form['tools_settings']['search'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('Search tools'),
      '#placeholder' => $this->t('Search by tool name or description...'),
      '#attributes'  => [
        'class'        => ['mcp-tools-search'],
        'autocomplete' => 'off',
      ],
      '#description' => $this->t(
        'Filter tools by name or description. Minimum 3 characters will auto-expand matching tools.'
      ),
      '#weight'      => -100,
    ];

    $form['tools_settings']['tools'] = [
      '#type' => 'vertical_tabs',
    ];

    $tools_config = $plugin_config['tools'] ?? [];
    $role_options = $this->getRoleOptions();

    foreach ($tools as $tool) {
      $this->buildSingleToolSection(
        $form['tools_settings'], $tool, $tools_config, $plugin_config,
        $plugin->getPluginId(), $role_options
      );
    }
  }

  /**
   * Build configuration for a single tool.
   */
  protected function buildSingleToolSection(
    array &$container,
    $tool,
    array $tools_config,
    array $plugin_config,
    string $plugin_id,
    array $role_options,
  ): void {
    $tool_name = $tool->name;
    $tool_config = $tools_config[$tool_name] ?? [];

    $container[$tool_name] = [
      '#type'  => 'details',
      '#title' => $tool_name,
      '#group' => 'tools_settings][tools',
    ];

    $this->buildToolForm(
      $container[$tool_name], $tool, $tool_config, $role_options,
      $plugin_config, $plugin_id
    );
  }

  /**
   * Build form elements for a single tool.
   */
  protected function buildToolForm(
    array &$container,
    $tool,
    array $tool_config,
    array $role_options,
    array $plugin_config,
    string $plugin_id,
  ): void {
    $tool_name = $tool->name;
    $plugin_enabled = $plugin_config['enabled'] ?? TRUE;

    // Add tool controls in order.
    $this->addToolEnabledField(
      $container, $tool_config, $plugin_enabled
    );
    $this->addToolInfoField(
      $container, $tool_name, $tool->description, $plugin_id
    );
    $this->addToolAnnotationsField($container, $tool);
    $this->addToolDescriptionField(
      $container, $tool->description, $tool_config, $plugin_enabled
    );
    $this->addToolRolesField(
      $container, $tool_config, $role_options, $plugin_enabled
    );
  }

  /**
   * Add enabled checkbox field.
   */
  protected function addToolEnabledField(
    array &$container,
    array $tool_config,
    bool $plugin_enabled,
  ): void {
    $container['enabled'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Enabled'),
      '#default_value' => $tool_config['enabled'] ?? TRUE,
      '#disabled'      => !$plugin_enabled,
      '#weight'        => -10,
    ];
  }

  /**
   * Add tool information display field.
   */
  protected function addToolInfoField(
    array &$container,
    string $tool_name,
    ?string $tool_description,
    string $plugin_id,
  ): void {
    $container['info'] = [
      '#type'   => 'container',
      '#weight' => -5,
      '#markup' => $this->formatToolInfo(
        $tool_name, $tool_description, $plugin_id
      ),
    ];
  }

  /**
   * Format tool information HTML.
   */
  protected function formatToolInfo(
    string $tool_name,
    ?string $tool_description,
    string $plugin_id,
  ): string {
    // Generate the machine name using the plugin's generateToolId method.
    $plugin = $this->pluginManager->createInstance($plugin_id);
    $machine_name = $plugin->generateToolId($plugin_id, $tool_name);

    return '<div>' .
      '<div>' . $this->t('Original Tool Information') . '</div>' .
      '<div><strong>' . $this->t('Original Name:') . '</strong> <code>'
      . Html::escape(
        $tool_name
      ) . '</code></div>' .
      '<div><strong>' . $this->t('Machine Name (For LLM):') . '</strong> <code>'
      . Html::escape(
        $machine_name
      ) . '</code></div>' .
      '<div><strong>' . $this->t('Description:') . '</strong> ' .
      Html::escape($tool_description ?? $this->t('No description available.'))
      . '</div>' .
      '</div>';
  }

  /**
   * Add tool annotations display field.
   */
  protected function addToolAnnotationsField(
    array &$container,
    $tool,
  ): void {
    if ($tool->annotations === NULL) {
      return;
    }

    $annotations = $tool->annotations;
    $hints = [];

    // Build list of annotation hints.
    if ($annotations->title !== NULL) {
      $hints[] = '<strong>' . $this->t('Title:') . '</strong> ' . Html::escape(
          $annotations->title
        );
    }
    if ($annotations->readOnlyHint !== NULL) {
      $hints[] = '<strong>' . $this->t('Read-only:') . '</strong> ' .
        ($annotations->readOnlyHint ? $this->t('Yes') : $this->t('No'));
    }
    if ($annotations->idempotentHint !== NULL) {
      $hints[] = '<strong>' . $this->t('Idempotent:') . '</strong> ' .
        ($annotations->idempotentHint ? $this->t('Yes') : $this->t('No'));
    }
    if ($annotations->destructiveHint !== NULL) {
      $hints[] = '<strong>' . $this->t('Destructive:') . '</strong> ' .
        ($annotations->destructiveHint ? $this->t('Yes') : $this->t('No'));
    }
    if ($annotations->openWorldHint !== NULL) {
      $hints[] = '<strong>' . $this->t('Open World:') . '</strong> ' .
        ($annotations->openWorldHint ? $this->t('Yes') : $this->t('No'));
    }

    // Only display if we have annotations.
    if (!empty($hints)) {
      $container['annotations'] = [
        '#type'   => 'container',
        '#weight' => -4,
        '#markup' => '<div class="mcp-tool-annotations">' .
        '<div><em>' . $this->t('Tool Behavior Hints:') . '</em></div>' .
        '<ul><li>' . implode('</li><li>', $hints) . '</li></ul>' .
        '</div>',
      ];
    }
  }

  /**
   * Add custom description field.
   */
  protected function addToolDescriptionField(
    array &$container,
    ?string $tool_description,
    array $tool_config,
    bool $plugin_enabled,
  ): void {
    $container['custom_description'] = [
      '#type'          => 'textarea',
      '#title'         => $this->t('Custom Description'),
      '#description'   => $this->t(
        'Custom description shown to MCP clients. Leave empty to use the original description.'
      ),
      '#default_value' => $tool_config['description'] ?? '',
      '#rows'          => 3,
      '#disabled'      => !$plugin_enabled,
    ];
  }

  /**
   * Add roles selection field.
   */
  protected function addToolRolesField(
    array &$container,
    array $tool_config,
    array $role_options,
    bool $plugin_enabled,
  ): void {
    $tool_roles = $tool_config['roles'] ?? [];
    $container['roles'] = [
      '#type'          => 'checkboxes',
      '#title'         => $this->t('Allowed roles'),
      '#description'   => $this->t(
        'Select roles that can access this tool. Leave empty to use plugin-level roles.'
      ),
      '#options'       => $role_options,
      '#default_value' => !empty($tool_roles) ? array_combine(
        $tool_roles, $tool_roles
      ) : [],
      '#disabled'      => !$plugin_enabled,
    ];
  }

  /**
   * Get available role options.
   */
  protected function getRoleOptions(): array {
    $role_options = [];
    foreach (Role::loadMultiple() as $role) {
      $role_options[$role->id()] = $role->label();
    }

    return $role_options;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    parent::validateForm($form, $form_state);

    // Get plugin from build info.
    $build_info = $form_state->getBuildInfo();
    $plugin = $build_info['args'][0] ?? NULL;
    if ($plugin instanceof McpInterface) {
      $plugin->validateConfigurationForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    // Get plugin from build info.
    $build_info = $form_state->getBuildInfo();
    $plugin = $build_info['args'][0] ?? NULL;
    if (!$plugin instanceof McpInterface) {
      return;
    }

    $plugin_id = $plugin->getPluginId();
    $config = $this->configFactory()->getEditable('mcp.settings');

    $plugin_config = $this->buildPluginConfiguration(
      $form_state, $plugin, $form
    );

    $config->set("plugins.$plugin_id", $plugin_config);
    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Build plugin configuration from form values.
   */
  protected function buildPluginConfiguration(
    FormStateInterface $form_state,
    McpInterface $plugin,
    array $form,
  ): array {
    $plugin_settings = $form_state->getValue('plugin_settings');
    $tools_settings = $form_state->getValue('tools_settings');

    return [
      'enabled' => (bool) $plugin_settings['enabled'],
      'roles'   => array_values(array_filter($plugin_settings['roles'] ?? [])),
      'config'  => $this->extractCustomConfiguration(
        $plugin_settings, $plugin, $form, $form_state
      ),
      'tools'   => $this->extractToolsConfiguration($tools_settings),
    ];
  }

  /**
   * Extract custom plugin configuration.
   */
  protected function extractCustomConfiguration(
    array $plugin_settings,
    McpInterface $plugin,
    array $form,
    FormStateInterface $form_state,
  ): array {
    if (!isset($plugin_settings['config'])) {
      return [];
    }

    $form_state->setValue('config', $plugin_settings['config']);
    $plugin->submitConfigurationForm($form, $form_state);

    return $plugin->getConfiguration()['config'] ?? [];
  }

  /**
   * Extract tools configuration.
   */
  protected function extractToolsConfiguration(?array $tools_settings): array {
    if (empty($tools_settings)) {
      return [];
    }

    $tools_config = [];
    foreach ($tools_settings as $tool_name => $tool_settings) {
      // Skip non-tool elements.
      if ($tool_name === 'tools' || !is_array($tool_settings)) {
        continue;
      }

      $tools_config[$tool_name] = $this->extractSingleToolConfiguration(
        $tool_settings
      );
    }

    return $tools_config;
  }

  /**
   * Extract configuration for a single tool.
   */
  protected function extractSingleToolConfiguration(
    array $tool_settings,
  ): array {
    return [
      'enabled'     => (bool) ($tool_settings['enabled'] ?? FALSE),
      'roles'       => $this->extractToolRoles($tool_settings),
      'description' => trim($tool_settings['custom_description'] ?? ''),
    ];
  }

  /**
   * Extract tool roles.
   */
  protected function extractToolRoles(array $tool_settings): array {
    if (isset($tool_settings['roles']) && is_array($tool_settings['roles'])) {
      return array_values(array_filter($tool_settings['roles']));
    }

    return [];
  }

}
