<?php

namespace Drupal\entity_mesh\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuTreeStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Entity Mesh settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'entity_mesh.settings';

  /**
   * Supported Source Entities.
   *
   * @var array<string>
   */
  const SUPPORTED_SOURCE_ENTITIES = [
    'node',
  ];

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The menu tree storage.
   *
   * @var \Drupal\Core\Menu\MenuTreeStorageInterface
   */
  protected $menuTreeStorage;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Menu\MenuTreeStorageInterface $menu_tree_storage
   *   The menu tree storage.
   */
  final public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    Connection $database,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    EntityTypeManagerInterface $entity_type_manager,
    MenuTreeStorageInterface $menu_tree_storage,
  ) {
    $this->typedConfigManager = $typedConfigManager;
    parent::__construct($config_factory, $typedConfigManager);
    $this->database = $database;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->entityTypeManager = $entity_type_manager;
    $this->menuTreeStorage = $menu_tree_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('database'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get(MenuTreeStorageInterface::class)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'entity_mesh_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['entity_mesh.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(self::SETTINGS);

    $form['global'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Global'),
    ];

    $form['global']['self_domain_internal'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Consider self-domain URLs as internal'),
      '#description' => $this->t('When enabled, absolute URLs that point to the current domain (e.g., https://mydomain/en-int) will be treated as internal URLs.'),
      '#default_value' => $config->get('self_domain_internal'),
    ];

    $form['global']['processing_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Dependency calculation mode'),
      '#options' => [
        'asynchronous' => $this->t('Asynchronous'),
        'synchronous' => $this->t('Synchronous'),
      ],
      '#default_value' => $config->get('processing_mode') ?: 'asynchronous',
      '#required' => TRUE,
    ];

    $form['global']['synchronous_processing_mode'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="processing_mode"]' => ['value' => 'synchronous'],
        ],
      ],
      'description' => [
        '#markup' => '<div class="description">' . $this->t('In synchronous mode, entities with few dependencies (below the configured limit) are processed immediately during save operations. Entities with many dependencies are automatically queued to prevent timeouts. This mode is ideal when you need immediate updates for simple content while maintaining stability for complex content.') . '</div>',
      ],
    ];

    $form['global']['asynchronous_processing_mode'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="processing_mode"]' => ['value' => 'asynchronous'],
        ],
      ],
      'description' => [
        '#markup' => '<div class="description">' . $this->t('In asynchronous mode, all dependency calculations are added to a queue and processed in the background. This prevents any impact on the user experience during entity save operations, ensuring fast response times regardless of the number of dependencies.') . '</div>',
      ],
    ];

    $form['global']['synchronous_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Synchronous processing threshold'),
      '#description' => $this->t('Entities with this number of dependencies or fewer will be processed immediately. Entities exceeding this threshold will be queued for background processing to prevent timeouts.'),
      '#default_value' => $config->get('synchronous_limit') ?: 25,
      '#min' => 1,
      '#max' => 9999,
      '#states' => [
        'visible' => [
          ':input[name="processing_mode"]' => ['value' => 'synchronous'],
        ],
        'required' => [
          ':input[name="processing_mode"]' => ['value' => 'synchronous'],
        ],
      ],
    ];

    $form['global']['debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Debug mode'),
      '#default_value' => $config->get('debug'),
      '#description' => $this->t('Enable debug mode for entity mesh processing. This will log additional information during processing.'),
    ];

    // Get analyzer account configuration.
    $analyzer_account = $config->get('analyzer_account') ?: ['type' => 'anonymous', 'roles' => NULL, 'user_id' => NULL];
    $form['global']['analyzer_account'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Analyzer account'),
      '#description' => $this->t('Select the account configuration to use when analyzing entity links. This determines which links are accessible during the analysis.'),
    ];

    $form['global']['analyzer_account']['account_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Account type'),
      '#options' => [
        'anonymous' => $this->t('Anonymous user'),
        'authenticated' => $this->t('Authenticated user with selected roles'),
        'user' => $this->t('Specific user'),
      ],
      '#default_value' => $analyzer_account['type'],
      '#required' => TRUE,
    ];

    // Get all available roles except anonymous and authenticated.
    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    $role_options = [];
    foreach ($roles as $role_id => $role) {
      if (!in_array($role_id, ['anonymous', 'authenticated'])) {
        $role_options[$role_id] = $role->label();
      }
    }

    $form['global']['analyzer_account']['roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Additional roles'),
      '#options' => $role_options,
      '#default_value' => $analyzer_account['roles'] ?: [],
      '#description' => $this->t('Select additional roles for the authenticated user. The authenticated role is automatically included.'),
      '#states' => [
        'visible' => [
          ':input[name="account_type"]' => ['value' => 'authenticated'],
        ],
      ],
    ];

    $form['global']['analyzer_account']['user_id'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('User'),
      '#target_type' => 'user',
      '#default_value' => $analyzer_account['user_id'] ? $this->entityTypeManager->getStorage('user')->load($analyzer_account['user_id']) : NULL,
      '#description' => $this->t('Select a specific user account to use for analysis.'),
      '#states' => [
        'visible' => [
          ':input[name="account_type"]' => ['value' => 'user'],
        ],
        'required' => [
          ':input[name="account_type"]' => ['value' => 'user'],
        ],
      ],
    ];

    $form['source_types'] = [
      '#type' => 'details',
      '#title' => $this->t('Source types'),
      '#description' => $this->t('Enable entity types and select specific bundles to be considered by Entity Mesh. If an entity type is enabled but no bundles are selected, all its bundles will be included.'),
      '#open' => TRUE,
    ];

    $source_types_config = $config->get('source_types') ?? [];

    foreach (self::SUPPORTED_SOURCE_ENTITIES as $entity_type_id) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $entity_type_label = $entity_type->getLabel();
      $entity_config = $source_types_config[$entity_type_id] ?? ['enabled' => FALSE, 'bundles' => []];

      // Add the main enable checkbox for the entity type.
      $form['source_types']['source_' . $entity_type_id . '_enabled'] = [
        '#type' => 'checkbox',
        '#title' => $entity_type_label,
        '#default_value' => $entity_config['enabled'],
        '#attributes' => ['id' => 'edit-entity-mesh-' . $entity_type_id . '-enabled'],
      ];

      // Only show bundle selection if the entity type has bundles.
      if ($entity_type->hasKey('bundle')) {
        $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
        if (!empty($bundles)) {
          // Container for bundle checkboxes, dependent on
          // the main enable checkbox.
          $form['source_types'][$entity_type_id . '_bundles_wrapper'] = [
            '#type' => 'container',
            '#attributes' => ['class' => ['entity-mesh-bundles-wrapper']],
            '#states' => [
              'visible' => [
                ':input[id="edit-entity-mesh-' . $entity_type_id . '-enabled"]' => ['checked' => TRUE],
              ],
            ],
          ];

          $options = [];
          foreach ($bundles as $bundle_id => $bundle_info) {
            $options[$bundle_id] = $bundle_info['label'];
          }

          // Place bundle checkboxes inside the wrapper.
          $form['source_types'][$entity_type_id . '_bundles_wrapper']['source_' . $entity_type_id . '_bundles'] = [
            '#type' => 'checkboxes',
            '#title' => $this->t('Bundles for @entity_type', ['@entity_type' => $entity_type_label]),
            '#options' => $options,
            '#default_value' => [],
            '#description' => $this->t('If no bundles are selected, all bundles for this entity type will be considered enabled.'),
          ];

          $default_bundles = [];
          if (!empty($entity_config['bundles'])) {
            foreach ($entity_config['bundles'] as $bundle_id => $enabled) {
              if ($enabled) {
                $default_bundles[] = $bundle_id;
              }
            }
          }
          $form['source_types'][$entity_type_id . '_bundles_wrapper']['source_' . $entity_type_id . '_bundles']['#default_value'] = $default_bundles;
        }
      }
    }

    $form['target_types'] = [
      '#type' => 'details',
      '#title' => $this->t('Target types'),
      '#open' => TRUE,
    ];

    $form['target_types']['entities'] = [
      '#type' => 'details',
      '#title' => $this->t('Entities'),
      '#description' => $this->t('Enable entity types and select specific bundles to be considered as targets by Entity Mesh. If an entity type is enabled but no bundles are selected, all its bundles will be included. <br> <strong>Some listed entities do not have accessible routes and can be disabled or ignored.</strong>'),
      '#open' => TRUE,
    ];

    $target_types_config = $config->get('target_types.internal') ?? [];

    // Get all available entity types.
    $entity_types = $this->entityTypeManager->getDefinitions();

    foreach ($entity_types as $entity_type_id => $entity_type) {
      if (!($entity_type instanceof ContentEntityTypeInterface
          || $entity_type instanceof ConfigEntityTypeInterface)) {
        continue;
      }

      $entity_type_label = $entity_type->getLabel();
      $entity_config = $target_types_config[$entity_type_id] ?? ['enabled' => FALSE, 'bundles' => []];

      $form['target_types']['entities']['target_' . $entity_type_id . '_enabled'] = [
        '#type' => 'checkbox',
        '#title' => $entity_type_label,
        '#default_value' => $entity_config['enabled'],
        '#attributes' => ['id' => 'edit-entity-mesh-target-' . $entity_type_id . '-enabled'],
      ];

      // Only show bundle selection if the entity type has bundles.
      if ($entity_type->hasKey('bundle')) {
        $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
        if (!empty($bundles)) {
          $form['target_types']['entities'][$entity_type_id . '_bundles_wrapper'] = [
            '#type' => 'container',
            '#attributes' => ['class' => ['entity-mesh-target-scheme-wrapper']],
            '#states' => [
              'visible' => [
                ':input[id="edit-entity-mesh-target-' . $entity_type_id . '-enabled"]' => ['checked' => TRUE],
              ],
            ],
          ];

          $options = [];
          foreach ($bundles as $bundle_id => $bundle_info) {
            $options[$bundle_id] = $bundle_info['label'];
          }

          $form['target_types']['entities'][$entity_type_id . '_bundles_wrapper']['target_' . $entity_type_id . '_bundles'] = [
            '#type' => 'checkboxes',
            '#title' => $this->t('Bundles for @entity_type', ['@entity_type' => $entity_type_label]),
            '#options' => $options,
            '#default_value' => [],
            '#description' => $this->t('If no bundles are selected, all bundles for this entity type will be considered enabled.'),
          ];

          $default_bundles = [];
          if (!empty($entity_config['bundles'])) {
            foreach ($entity_config['bundles'] as $bundle_id => $enabled) {
              if ($enabled) {
                $default_bundles[] = $bundle_id;
              }
            }
          }
          $form['target_types']['entities'][$entity_type_id . '_bundles_wrapper']['target_' . $entity_type_id . '_bundles']['#default_value'] = $default_bundles;
        }
      }
    }

    $form['target_types']['external'] = [
      '#type' => 'details',
      '#title' => $this->t('External'),
      '#description' => $this->t('External target types.'),
      '#open' => TRUE,
    ];

    // Add external URL schemes as bundles.
    $form['target_types']['external']['external_scheme_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['entity-mesh-target-scheme-wrapper']],
    ];
    $external_scheme = [
      'http' => $this->t('HTTP URLs (http://, https://)'),
      'tel' => $this->t('Phone numbers (tel:)'),
      'mailto' => $this->t('Email addresses (mailto:)'),
    ];

    $form['target_types']['external']['external_scheme_wrapper']['target_external_scheme'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('URL schemes'),
      '#options' => $external_scheme,
      '#default_value' => $this->getExternalSchemeDefault($config),
    ];

    // Add separate wrapper for categories.
    $form['target_types']['external']['external_categories_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['entity-mesh-categories-wrapper']],
    ];

    $form['target_types']['external']['external_categories_wrapper']['target_external_categories'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Categories'),
      '#options' => [
        'iframe' => $this->t('Iframe'),
      ],
      '#default_value' => $this->getExternalCategoriesDefault($config),
      '#description' => $this->t('External content categories to process.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    // Validate that user_id is provided when account_type is 'user'.
    if ($form_state->getValue('account_type') === 'user') {
      $user_id = $form_state->getValue('user_id');
      if (empty($user_id)) {
        $form_state->setErrorByName('user_id', $this->t('Select a user when "Specific user" is selected as the account type.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config(self::SETTINGS);
    $processing_mode = $form_state->getValue('processing_mode');

    $config->set('self_domain_internal', $form_state->getValue('self_domain_internal'));
    $config->set('processing_mode', $processing_mode);
    $config->set('debug', $form_state->getValue('debug'));

    // Save analyzer account configuration.
    $account_type = $form_state->getValue('account_type');
    $analyzer_account = [
      'type' => $account_type,
      'roles' => NULL,
      'user_id' => NULL,
    ];

    if ($account_type === 'authenticated') {
      // Filter out unchecked roles and include authenticated.
      $selected_roles = array_filter($form_state->getValue('roles'));
      $analyzer_account['roles'] = array_values($selected_roles);
    }
    elseif ($account_type === 'user') {
      $analyzer_account['user_id'] = $form_state->getValue('user_id');
    }

    $config->set('analyzer_account', $analyzer_account);

    if ($processing_mode === 'synchronous') {
      $config->set('synchronous_limit', $form_state->getValue('synchronous_limit'));
    }

    // Process and save source types with the new structure.
    $source_types_config = [];
    foreach (self::SUPPORTED_SOURCE_ENTITIES as $entity_type_id) {
      $entity_enabled = (bool) $form_state->getValue('source_' . $entity_type_id . '_enabled');
      $submitted_bundles = $form_state->getValue('source_' . $entity_type_id . '_bundles');
      if ($entity_enabled) {
        $source_types_config[$entity_type_id]['enabled'] = TRUE;
        $source_types_config[$entity_type_id]['bundles'] = $this->getEnabledBundlesConfig($entity_type_id, $submitted_bundles);
      }

    }
    $config->set('source_types', $source_types_config);

    // Process and save target types.
    $target_types_config = [];
    $entity_types = $this->entityTypeManager->getDefinitions();

    foreach ($entity_types as $entity_type_id => $entity_type) {
      if (!($entity_type instanceof ContentEntityTypeInterface
          || $entity_type instanceof ConfigEntityTypeInterface)) {
        continue;
      }

      $entity_enabled = (bool) $form_state->getValue('target_' . $entity_type_id . '_enabled');
      $submitted_bundles = $form_state->getValue('target_' . $entity_type_id . '_bundles');
      if ($entity_enabled) {
        $target_types_config['internal'][$entity_type_id]['enabled'] = TRUE;
        $target_types_config['internal'][$entity_type_id]['bundles'] = $this->getEnabledBundlesConfig($entity_type_id, $submitted_bundles);
      }
    }
    // Update external bundles handling.
    $external_bundles = $form_state->getValue('target_external_scheme');
    foreach ($external_bundles as $key => $value) {
      if (!empty($value)) {
        $target_types_config['external']['scheme'][$key] = TRUE;
      }

    }

    // Save external categories.
    $external_categories = $form_state->getValue('target_external_categories');
    foreach ($external_categories as $key => $value) {
      if (!empty($value)) {
        $target_types_config['external']['categories'][$key] = TRUE;
      }

    }

    if ($form_state->getValue('target_view')) {
      $target_types_config['internal']['view']['enabled'] = TRUE;
    }

    $config->set('target_types', $target_types_config);

    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Helper to build enabled/bundles config for an entity type.
   */
  private function getEnabledBundlesConfig($entity_type_id, $submitted_bundles) {
    $bundles_boolean_map = [];
    $all_bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);

    if (!empty($all_bundles)) {
      foreach (array_keys($all_bundles) as $bundle_id) {
        if (!empty($submitted_bundles[$bundle_id])) {
          $bundles_boolean_map[$bundle_id] = TRUE;
        }
      }

    }
    return $bundles_boolean_map;
  }

  /**
   * Helper to get default values for external categories.
   */
  private function getExternalCategoriesDefault($config) {
    $defaults = [];
    if ($config->get('target_types.external.categories.iframe')) {
      $defaults[] = 'iframe';
    }
    return $defaults;
  }

  /**
   * Helper to get default values for external bundles.
   */
  private function getExternalSchemeDefault($config) {
    $defaults = [];
    if ($config->get('target_types.external.scheme.tel')) {
      $defaults[] = 'tel';
    }
    if ($config->get('target_types.external.scheme.mailto')) {
      $defaults[] = 'mailto';
    }
    if ($config->get('target_types.external.scheme.http')) {
      $defaults[] = 'http';
    }
    return $defaults;
  }

}
