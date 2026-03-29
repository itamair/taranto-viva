<?php

declare(strict_types=1);

namespace Drupal\mcp\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Model Context Protocol settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The MCP plugin manager.
   *
   * @var \Drupal\mcp\Plugin\McpPluginManager
   */
  protected $pluginManagerMcp;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $form = parent::create($container);
    $form->pluginManagerMcp = $container->get('plugin.manager.mcp');
    $form->entityTypeManager = $container->get('entity_type.manager');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mcp_settings';
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
  ): array {
    $config = $this->config('mcp.settings');

    // General Settings container.
    $form['general'] = [
      '#type'  => 'fieldset',
      '#title' => $this->t('Authentication'),
    ];

    $form['general']['enable_auth'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Enable Auth'),
      '#description'   => $this->t(
        'Check to enable authentication for the MCP server. If disabled, the server will allow clients to connect with anonymous permissions.'
      ),
      '#default_value' => $config->get('enable_auth') ?? FALSE,
    ];

    $form['general']['auth_settings'] = [
      '#type'   => 'fieldset',
      '#title'  => $this->t('Authentication Settings'),
      '#states' => [
        'visible' => [
          ':input[name="enable_auth"]' => ['checked' => TRUE],
        ],
      ],
      '#tree'   => TRUE,
    ];

    $form['general']['auth_settings']['enable_token_auth'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Enable Token Auth'),
      '#description'   => $this->t(
        'Check to enable token-based authentication. Token authentication allows access as a specific user.'
      ),
      '#default_value' => $config->get('auth_settings.enable_token_auth') ?? FALSE,
    ];

    $form['general']['auth_settings']['token_with_generation'] = [
      '#type'   => 'container',
      '#states' => [
        'visible' => [
          ':input[name="enable_token_auth"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['general']['auth_settings']['token_with_generation']['token_key'] = [
      '#type'          => 'key_select',
      '#title'         => $this->t('Secret key'),
      '#default_value' => $config->get('auth_settings.token_key') ?? '',
      '#states'        => [
        'visible' => [
          ':input[name="auth_settings[enable_token_auth]"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="auth_settings[enable_token_auth]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['general']['auth_settings']['token_with_generation']['token_user'] = [
      '#type'          => 'entity_autocomplete',
      '#title'         => $this->t('Token User'),
      '#description'   => $this->t(
        'Select the user account that will be used for token authentication.'
      ),
      '#target_type'   => 'user',
      '#default_value' => $config->get('auth_settings.token_user') ?
      $this->entityTypeManager->getStorage('user')->load(
          $config->get('auth_settings.token_user')
      ) : NULL,
      '#states'        => [
        'visible' => [
          ':input[name="auth_settings[enable_token_auth]"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="auth_settings[enable_token_auth]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['general']['auth_settings']['enable_basic_auth'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Enable Basic Auth'),
      '#description'   => $this->t(
        'Check to enable Basic Authentication using a username and password. Authentication will enforce role-based permissions.'
      ),
      '#default_value' => $config->get('auth_settings.enable_basic_auth') ??
      FALSE,
    ];

    $form['general']['auth_settings']['oauth_info'] = [
      '#type'   => 'container',
      '#markup' => '<div class="messages messages--info">' .
      '<h3>' . $this->t('OAuth Authentication') . '</h3>' .
      '<p>' . $this->t(
          'OAuth authentication is also supported. If your Drupal site is configured with an OAuth provider, MCP clients will be able to authenticate using OAuth automatically. No additional configuration is required here.'
      ) . '</p>' .
      '</div>',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    parent::validateForm($form, $form_state);

    if ($form_state->getValue('enable_auth')) {
      $auth_settings = $form_state->getValue('auth_settings');
      $enable_token_auth = !empty($auth_settings['enable_token_auth']);
      $enable_basic_auth = !empty($auth_settings['enable_basic_auth']);
      if (!$enable_token_auth && !$enable_basic_auth) {
        $form_state->setErrorByName(
          'auth_settings', $this->t(
          'At least one authentication method must be selected if Auth is enabled.'
          )
        );
      }
      if ($enable_token_auth) {
        $token_key = $auth_settings['token_with_generation']['token_key'] ?? '';
        if (empty($token_key)) {
          $form_state->setErrorByName(
            'token_with_generation][token_key', $this->t(
            'Secret key must be provided when token authentication is enabled.'
            )
          );
        }

        // Validate token user selection.
        $token_user = $auth_settings['token_with_generation']['token_user'] ?? '';
        if (empty($token_user)) {
          $form_state->setErrorByName(
            'token_with_generation][token_user', $this->t(
            'A user must be selected for token authentication.'
            )
          );
        }
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    // Load our config object for editing.
    $config = $this->config('mcp.settings');

    // Save general settings.
    $auth_settings = $form_state->getValue('auth_settings');
    $enable_auth = (bool) $form_state->getValue('enable_auth');
    $config->set('enable_auth', $enable_auth);
    $config->set(
      'auth_settings', $enable_auth ? [
        'enable_token_auth' => !empty($auth_settings['enable_token_auth']) ? $auth_settings['enable_token_auth'] : FALSE,
        'token_key' => $auth_settings['token_with_generation']['token_key'] ?? '',
        'token_user' => $auth_settings['token_with_generation']['token_user'] ?? '',
        'enable_basic_auth' => !empty($auth_settings['enable_basic_auth']) ? $auth_settings['enable_basic_auth'] : FALSE,
      ] : []
    );

    $config->save();

    // Call parent submit to show success message, etc.
    parent::submitForm($form, $form_state);
  }

}
