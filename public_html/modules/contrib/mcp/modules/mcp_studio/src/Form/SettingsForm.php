<?php

namespace Drupal\mcp_studio\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Configure MCP Studio settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mcp_studio_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['mcp_studio.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('mcp_studio.settings');
    $tools = $config->get('tools') ?? [];

    $form['description'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('Create and manage MCP tools with static responses for testing and development.') . '</p>',
    ];

    $form['tools_table'] = [
      '#type' => 'table',
      '#caption' => $this->t('Studio Tools'),
      '#header' => [
        $this->t('Name'),
        $this->t('Description'),
        $this->t('Operations'),
      ],
      '#empty' => $this->t('No tools configured yet.'),
    ];

    foreach ($tools as $index => $tool) {
      $form['tools_table'][$index]['name'] = [
        '#plain_text' => $tool['name'] ?? '',
      ];
      $form['tools_table'][$index]['description'] = [
        '#plain_text' => $tool['description'] ?? '',
      ];
      $form['tools_table'][$index]['operations'] = [
        '#type' => 'operations',
        '#links' => [
          'edit' => [
            'title' => $this->t('Edit'),
            'url' => Url::fromRoute('mcp_studio.tool_form', ['tool_id' => $index]),
          ],
          'delete' => [
            'title' => $this->t('Delete'),
            'url' => Url::fromRoute('mcp_studio.tool_delete', ['tool_id' => $index]),
          ],
        ],
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['add_tool'] = [
      '#type' => 'link',
      '#title' => $this->t('Add tool'),
      '#url' => Url::fromRoute('mcp_studio.tool_form'),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
    ];

    return $form;
  }

}
