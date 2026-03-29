<?php

namespace Drupal\mcp_studio\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for adding/editing a Studio tool.
 */
class ToolForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mcp_studio_tool_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $tool_id = NULL) {
    $config = $this->configFactory()->getEditable('mcp_studio.settings');
    $tools = $config->get('tools') ?? [];
    $tool = NULL;

    if ($tool_id !== NULL && isset($tools[$tool_id])) {
      $tool = $tools[$tool_id];
      $form_state->set('tool_id', $tool_id);
    }

    // Set form title based on whether we're creating or editing.
    $form['#title'] = $tool_id !== NULL ? $this->t('Update the MCP Tool') : $this->t('Create new MCP Tool');

    // Attach the CodeMirror library to the entire form.
    $form['#attached']['library'][] = 'mcp_studio/codemirror';

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tool name'),
      '#description' => $this->t('The name of the tool as it will appear in MCP.'),
      '#required' => TRUE,
      '#default_value' => $tool['name'] ?? '',
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#description' => $this->t('A detailed description of what this tool does.'),
      '#required' => TRUE,
      '#default_value' => $tool['description'] ?? '',
      '#rows' => 5,
    ];

    $form['input_schema'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Input schema (JSON Schema)'),
      '#description' => $this->t('JSON Schema defining the tool"s input parameters. Leave empty or use {} for no parameters.'),
      '#default_value' => $tool['input_schema'] ?? '{}',
      '#rows' => 10,
      '#attributes' => [
        'class' => ['codemirror-editor', 'json-schema-editor'],
        'data-mode' => 'json',
        'placeholder' => '{
  "type": "object",
  "properties": {
    "message": {
      "type": "string"
    }
  }
}',
      ],
    ];

    // Container for output fields.
    $form['output_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['output-field-group']],
      '#prefix' => '<div class="output-section">',
      '#suffix' => '</div>',
    ];

    // Mode selector.
    $form['output_wrapper']['output_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Output format'),
      '#options' => [
        'text' => $this->t('Plain Text'),
        'json' => $this->t('JSON'),
        'twig' => $this->t('TWIG Template'),
      ],
      '#default_value' => $tool['output_mode'] ?? 'text',
      '#attributes' => ['class' => ['output-mode-selector']],
    ];

    // Output textarea.
    $form['output_wrapper']['output'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Output content'),
      '#description' => $this->t('The response this tool will return.'),
      '#required' => TRUE,
      '#default_value' => $tool['output'] ?? '',
      '#rows' => 15,
      '#attributes' => [
        'class' => ['codemirror-editor'],
        'data-mode' => $tool['output_mode'] ?? 'text',
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $tool_id !== NULL ? $this->t('Save tool') : $this->t('Add tool'),
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('mcp_studio.settings'),
      '#attributes' => [
        'class' => ['button'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate input schema.
    $input_schema = $form_state->getValue('input_schema');
    if (!empty($input_schema) && $input_schema !== '{}') {
      $decoded = json_decode($input_schema, TRUE);
      if (json_last_error() !== JSON_ERROR_NONE) {
        $form_state->setErrorByName('input_schema', $this->t('Input schema must be valid JSON. Error: @error', [
          '@error' => json_last_error_msg(),
        ]));
      }
      else {
        // Validate it's a proper JSON Schema.
        if (!is_array($decoded)) {
          $form_state->setErrorByName('input_schema', $this->t('Input schema must be a JSON object.'));
        }
        else {
          // Check for valid JSON Schema structure.
          if (!isset($decoded['type']) && !isset($decoded['$ref']) && !isset($decoded['$schema'])) {
            $form_state->setErrorByName('input_schema', $this->t('Input schema must be a valid JSON Schema. At minimum, it should have a "type" property.'));
          }

          // Validate common JSON Schema types.
          if (isset($decoded['type'])) {
            $valid_types = [
              'null',
              'boolean',
              'object',
              'array',
              'number',
              'string',
              'integer',
            ];
            if (!in_array($decoded['type'], $valid_types)) {
              $form_state->setErrorByName('input_schema', $this->t('Invalid JSON Schema type "@type". Valid types are: @types', [
                '@type' => $decoded['type'],
                '@types' => implode(', ', $valid_types),
              ]));
            }

            // Additional validation for object types.
            if ($decoded['type'] === 'object' && isset($decoded['properties'])) {
              if (!is_array($decoded['properties'])) {
                $form_state->setErrorByName('input_schema', $this->t('JSON Schema "properties" must be an object.'));
              }
              else {
                // Validate each property has a type.
                foreach ($decoded['properties'] as $prop_name => $prop_schema) {
                  if (is_array($prop_schema) && !isset($prop_schema['type']) && !isset($prop_schema['$ref'])) {
                    $form_state->setErrorByName('input_schema', $this->t('Property "@prop" must have a "type" defined.', [
                      '@prop' => $prop_name,
                    ]));
                  }
                }
              }
            }

            // Validate required array.
            if (isset($decoded['required']) && !is_array($decoded['required'])) {
              $form_state->setErrorByName('input_schema', $this->t('JSON Schema "required" must be an array of property names.'));
            }
          }
        }
      }
    }

    // Validate output based on mode.
    $output_mode = $form_state->getValue('output_mode');
    $output = $form_state->getValue('output');

    if (!empty($output)) {
      switch ($output_mode) {
        case 'json':
          // Validate JSON syntax.
          $decoded = json_decode($output);
          if (json_last_error() !== JSON_ERROR_NONE) {
            $form_state->setErrorByName('output', $this->t('Output must be valid JSON when JSON mode is selected. Error: @error', [
              '@error' => json_last_error_msg(),
            ]));
          }
          break;

        case 'twig':
          // Comprehensive TWIG validation.
          // Check for balanced delimiters.
          $open_tags = substr_count($output, '{%');
          $close_tags = substr_count($output, '%}');
          if ($open_tags !== $close_tags) {
            $form_state->setErrorByName('output', $this->t('TWIG syntax error: Unbalanced {% %} tags. Found @open opening and @close closing tags.', [
              '@open' => $open_tags,
              '@close' => $close_tags,
            ]));
          }

          $open_vars = substr_count($output, '{{');
          $close_vars = substr_count($output, '}}');
          if ($open_vars !== $close_vars) {
            $form_state->setErrorByName('output', $this->t('TWIG syntax error: Unbalanced {{ }} variables. Found @open opening and @close closing variables.', [
              '@open' => $open_vars,
              '@close' => $close_vars,
            ]));
          }

          $open_comments = substr_count($output, '{#');
          $close_comments = substr_count($output, '#}');
          if ($open_comments !== $close_comments) {
            $form_state->setErrorByName('output', $this->t('TWIG syntax error: Unbalanced {# #} comments. Found @open opening and @close closing comments.', [
              '@open' => $open_comments,
              '@close' => $close_comments,
            ]));
          }

          // Check if there's at least one TWIG construct.
          if ($open_tags === 0 && $open_vars === 0 && $open_comments === 0) {
            // Allow plain text in TWIG mode.
            if (strpos($output, '{') !== FALSE || strpos($output, '}') !== FALSE) {
              $form_state->setErrorByName('output', $this->t('Invalid TWIG syntax: Found incomplete TWIG delimiters. Use {{ }} for variables, {% %} for tags, or {# #} for comments.'));
            }
          }

          // Check for common TWIG syntax errors.
          if (preg_match('/\{\{[^}]*\{[{%]/', $output)) {
            $form_state->setErrorByName('output', $this->t('TWIG syntax error: Nested opening delimiters detected.'));
          }

          if (preg_match('/[}%]\}[^{]*\}\}/', $output)) {
            $form_state->setErrorByName('output', $this->t('TWIG syntax error: Nested closing delimiters detected.'));
          }

          // Validate common TWIG structures.
          if (preg_match_all('/\{%\s*(\w+)/', $output, $matches)) {
            $valid_tags = [
              'if',
              'else',
              'elseif',
              'endif',
              'for',
              'endfor',
              'set',
              'block',
              'endblock',
              'extends',
              'include',
              'import',
              'from',
              'macro',
              'endmacro',
            ];

            foreach ($matches[1] as $tag) {
              if (!in_array($tag, $valid_tags)) {
                $form_state->setErrorByName('output', $this->t('TWIG syntax error: Unknown tag "@tag". Common tags are: if, for, set, block, include.', [
                  '@tag' => $tag,
                ]));
              }
            }
          }
          break;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('mcp_studio.settings');
    $tools = $config->get('tools') ?? [];

    $tool_data = [
      'name' => $form_state->getValue('name'),
      'description' => $form_state->getValue('description'),
      'input_schema' => $form_state->getValue('input_schema'),
      'output' => $form_state->getValue('output'),
      'output_mode' => $form_state->getValue('output_mode'),
    ];

    $tool_id = $form_state->get('tool_id');
    if ($tool_id !== NULL) {
      $tools[$tool_id] = $tool_data;
      $this->messenger()->addStatus($this->t('Tool updated successfully.'));
    }
    else {
      $tools[] = $tool_data;
      $this->messenger()->addStatus($this->t('Tool added successfully.'));
    }

    $config->set('tools', $tools)->save();
    $form_state->setRedirectUrl(Url::fromRoute('mcp_studio.settings'));
  }

}
