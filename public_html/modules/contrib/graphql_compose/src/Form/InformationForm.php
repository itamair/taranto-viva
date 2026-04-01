<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Form;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Info GraphQL Compose settings form.
 */
class InformationForm extends ConfigFormBase {

  /**
   * The UUID service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected UuidInterface $uuid;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);

    $instance->uuid = $container->get('uuid');
    $instance->moduleHandler = $container->get('module_handler');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'graphql_compose_info';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['graphql_compose.settings'];
  }

  /**
   * {@inheritdoc}
   */
  protected function getConfig() {
    return $this->config('graphql_compose.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['info'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Schema information'),
      '#description' => $this->t('Add version information to the %query query.', [
        '%query' => 'info',
      ]),
    ];

    $form['info']['schema_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Schema version'),
      '#default_value' => $this->getConfig()->get('settings.schema_version'),
      '#size' => 10,
    ];

    $form['info']['schema_description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Schema description'),
      '#default_value' => $this->getConfig()->get('settings.schema_description'),
      '#maxlength' => 255,
    ];

    $form['site'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Site information'),
      '#description' => $this->t('Add site information to the %query query.', [
        '%query' => 'info',
      ]),
    ];

    $form['site']['site_name'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add site name'),
      '#default_value' => $this->getConfig()->get('settings.site_name'),
    ];

    $form['site']['site_slogan'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add site slogan'),
      '#default_value' => $this->getConfig()->get('settings.site_slogan'),
    ];

    $form['site']['site_front'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add home path'),
      '#default_value' => $this->getConfig()->get('settings.site_front'),
    ];

    $form['custom'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Custom content'),
      '#description' => $this->t('Add custom content to the %query query.', [
        '%query' => 'info',
      ]),
      '#tree' => TRUE,
    ];

    $form['custom']['settings'] = [
      '#type' => 'table',
      '#prefix' => '<div id="custom-settings-wrapper">',
      '#suffix' => '</div>',
      '#header' => [
        $this->t('Field name'),
        $this->t('Description'),
        $this->t('Value'),
        $this->t('Type'),
        $this->t('Operation'),
        $this->t('Weight'),
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
        ],
      ],
      '#empty' => $this->t('No custom content added yet.'),
    ];

    if (is_null($form_state->get('custom_fields'))) {
      $form_state->set(
        'custom_fields',
        $this->getConfig()->get('settings.custom')
      );
    }
    $fields = $form_state->get('custom_fields') ?: [];

    // Sort fields by weight value.
    uasort($fields, function ($a, $b) {
      return $a['weight'] <=> $b['weight'];
    });

    foreach ($fields as $uuid => $field) {

      $form['custom']['settings'][$uuid]['#attributes']['class'][] = 'draggable';
      $form['custom']['settings'][$uuid]['#weight'] = $field['weight'];

      $form['custom']['settings'][$uuid]['name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Field name'),
        '#title_display' => 'invisible',
        '#required' => TRUE,
        '#default_value' => $field['name'],
        '#placeholder' => $this->t('Field name'),
        '#maxlength' => 50,
        '#size' => 25,
        '#element_validate' => [SchemaForm::class . '::validateNameSdl'],
      ];

      $form['custom']['settings'][$uuid]['description'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Value'),
        '#title_display' => 'invisible',
        '#default_value' => $field['description'],
        '#placeholder' => $this->t('Description'),
        '#maxlength' => 255,
      ];

      $form['custom']['settings'][$uuid]['value'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Value'),
        '#title_display' => 'invisible',
        '#required' => TRUE,
        '#default_value' => $field['value'],
        '#placeholder' => $this->t('Value'),
        '#maxlength' => 255,
        '#element_validate' => ['::validateTypeValue'],
      ];

      if ($this->moduleHandler->moduleExists('token')) {
        $form['custom']['settings'][$uuid]['value']['#element_validate'][] = 'token_element_validate';
        $form['custom']['settings'][$uuid]['value']['#token_types'] = [];
      }

      $form['custom']['settings'][$uuid]['type'] = [
        '#type' => 'select',
        '#title' => $this->t('Type'),
        '#title_display' => 'invisible',
        '#required' => TRUE,
        '#options' => [
          'boolean' => $this->t('Boolean'),
          'float' => $this->t('Float'),
          'int' => $this->t('Integer'),
          'string' => $this->t('String'),
        ],
        '#default_value' => $field['type'],
        '#element_validate' => ['::validateMatchingCustomType'],
      ];

      $form['custom']['settings'][$uuid]['remove'] = [
        '#name' => 'remove-' . $uuid,
        '#type' => 'submit',
        '#value' => $this->t('Delete'),
        '#submit' => ['::removeCustomField'],
        '#ajax' => [
          'callback' => '::removeCustomFieldCallback',
          'wrapper' => 'custom-settings-wrapper',
        ],
        '#attributes' => [
          'class' => ['button--small', 'button--danger'],
        ],
        '#limit_validation_errors' => [],
      ];

      // TableDrag: Weight column element.
      $form['custom']['settings'][$uuid]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight'),
        '#title_display' => 'invisible',
        '#default_value' => $field['weight'],
        '#attributes' => [
          'class' => [
            'table-sort-weight',
          ],
        ],
      ];
    }

    if ($this->moduleHandler->moduleExists('token')) {
      $form['custom']['tokens'] = [
        '#type' => 'container',
      ];

      $form['custom']['tokens']['token_help'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => [],
      ];
    }

    $form['custom']['add'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add custom setting'),
      '#ajax' => [
        'callback' => '::addCustomFieldCallback',
        'wrapper' => 'custom-settings-wrapper',
      ],
      '#attributes' => [
        'class' => ['button--small'],
      ],
      '#limit_validation_errors' => [],
      '#submit' => ['::addCustomField'],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Add a custom field.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function addCustomField(array &$form, FormStateInterface $form_state): void {
    $form_state->setRebuild();

    $uuid = $this->uuid->generate();
    $fields = $form_state->get('custom_fields') ?: [];

    $fields[$uuid] = [
      'uuid' => $uuid,
      'name' => '',
      'description' => '',
      'value' => '',
      'type' => 'string',
      'weight' => 0,
    ];

    $form_state->set('custom_fields', $fields);
  }

  /**
   * Add a custom field ajax callback.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element.
   */
  public function addCustomFieldCallback(array &$form, FormStateInterface $form_state) {
    return $form['custom']['settings'];
  }

  /**
   * Remove a custom field.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function removeCustomField(array &$form, FormStateInterface $form_state): void {
    $form_state->setRebuild();

    $triggering_element = $form_state->getTriggeringElement();
    $parents = $triggering_element['#array_parents'];
    $uuid = array_slice($parents, -2, 1)[0];

    $fields = $form_state->get('custom_fields');
    unset($fields[$uuid]);

    $form_state->set('custom_fields', $fields);
  }

  /**
   * Remove a custom field ajax callback.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element.
   */
  public function removeCustomFieldCallback(array &$form, FormStateInterface $form_state): array {
    return $form['custom']['settings'];
  }

  /**
   * Ensure the selected type matches the data value (roughly).
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateTypeValue($element, FormStateInterface $form_state): void {
    $parents = $element['#array_parents'];
    $uuid = array_slice($parents, -2, 1)[0];

    $value = $element['#value'];
    $type = $form_state->getValue(['custom', 'settings', $uuid, 'type']);

    switch ($type) {
      case 'string':
        if (!is_string($value)) {
          $form_state->setError($element, $this->t('The value must be a string.'));
        }
        break;

      case 'float':
        if (filter_var($value, FILTER_VALIDATE_FLOAT) === FALSE) {
          $form_state->setError($element, $this->t('The value must be a float.'));
        }
        break;

      case 'int':
        if (filter_var($value, FILTER_VALIDATE_INT) === FALSE) {
          $form_state->setError($element, $this->t('The value must be an integer.'));
        }
        break;

      case 'boolean':
        if (filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === NULL) {
          $form_state->setError($element, $this->t('The value must be a boolean.'));
        }
        break;
    }
  }

  /**
   * Allow the same name to be defined, but ensure its the same type.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateMatchingCustomType($element, FormStateInterface $form_state): void {
    $parents = $element['#array_parents'];
    $uuid = array_slice($parents, -2, 1)[0];

    $type = $element['#value'];
    $name = $form_state->getValue(['custom', 'settings', $uuid, 'name']);
    $fields = $form_state->getValue(['custom', 'settings']);

    foreach ($fields as $field) {
      if ($field['name'] === $name && $field['type'] !== $type) {
        $form_state->setError(
          $element,
          $this->t('Fields with matching names must have matching types.')
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $custom_settings = [];
    $custom_values = $form_state->getValue(['custom', 'settings']) ?: [];

    foreach ($custom_values as $uuid => $values) {
      $custom_settings[$uuid] = [
        'name' => $values['name'],
        'description' => $values['description'] ?: NULL,
        'value' => $values['value'],
        'type' => $values['type'],
        'weight' => $values['weight'],
      ];
    }

    $this->getConfig()
      ->set('settings.schema_description', $form_state->getValue('schema_description') ?: NULL)
      ->set('settings.schema_version', $form_state->getValue('schema_version') ?: NULL)
      ->set('settings.site_front', $form_state->getValue('site_front'))
      ->set('settings.site_name', $form_state->getValue('site_name'))
      ->set('settings.site_slogan', $form_state->getValue('site_slogan'))
      ->set('settings.custom', $custom_settings)
      ->save();

    _graphql_compose_cache_flush();

    parent::submitForm($form, $form_state);
  }

}
