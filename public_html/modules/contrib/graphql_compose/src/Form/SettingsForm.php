<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Form;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure GraphQL Compose settings form.
 */
class SettingsForm extends ConfigFormBase {

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
    return 'graphql_compose_settings';
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

    $form['entities'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Entity options'),
    ];

    $form['entities']['expose_entity_ids'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expose entity IDs'),
      '#description' => $this->t('
        Enable to expose your entity IDs in the schema.
        The schema will always have UUIDs enabled.
        Leaving this disabled can help protect against enumeration attacks.
      '),
      '#default_value' => $this->getConfig()->get('settings.expose_entity_ids'),
    ];

    $form['entities']['simple_queries'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Simple entity queries'),
      '#description' => $this->t('Enable to combine the entity queries (eg nodePage, nodeArticle) into a single query (node) that returns a Union.'),
      '#default_value' => $this->getConfig()->get('settings.simple_queries'),
    ];

    $form['entities']['exclude_unpublished'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Exclude unpublished entities'),
      '#description' => $this->t('Enable to exclude unpublished entities (ignoring permissions) from entity references and edges.'),
      '#default_value' => $this->getConfig()->get('settings.exclude_unpublished'),
    ];

    $form['fields'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Field options'),
    ];

    $form['fields']['field_required_override'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Required override'),
      '#description' => $this->t('
        Enable to override the required setting per field.
        Disable to automatically use the field settings.
      '),
      '#default_value' => $this->getConfig()->get('settings.field_required_override'),
    ];

    $form['fields']['simple_unions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Simple entity reference unions'),
      '#description' => $this->t('
        Enable to use a generic Union for entity reference fields.
        Disable to use a unique Union per entity reference field.
        Enabling this can help simplify the schema, depending on the use case.
      '),
      '#default_value' => $this->getConfig()->get('settings.simple_unions'),
    ];

    if ($this->moduleHandler->moduleExists('svg_image')) {

      $form['fields']['svg_image'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Embed SVG images'),
        '#default_value' => $this->getConfig()->get('settings.svg_image'),
        '#description' => $this->t('Allow embedding SVG content on Image types.'),
      ];

      $form['fields']['svg_filesize'] = [
        '#type' => 'number',
        '#title' => $this->t('Maximum filesize for SVG'),
        '#default_value' => $this->getConfig()->get('settings.svg_filesize') ?: 100,
        '#field_suffix' => $this->t('KB'),
        '#description' => $this->t('The maximum size in KB of a file allowed to be embedded within a request.'),
        '#min' => 0,
        '#required' => TRUE,
        '#states' => [
          'visible' => [
            ':input[name="svg_image"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }

    $form['inflector'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('String inflector'),
      '#description' => $this->t('The string inflector is used when renaming some types to try and avoid conflicts in naming conventions.'),
    ];

    $form['inflector']['inflector_langcode'] = [
      '#type' => 'select',
      '#title' => $this->t('Inflector language'),
      '#options' => [
        'en' => $this->t('English'),
        'fr' => $this->t('French'),
        'nb' => $this->t('Norwegian Bokmal'),
        'pt-pt' => $this->t('Portuguese'),
        'pt-br' => $this->t('Portuguese (Brazil)'),
        'es' => $this->t('Spanish'),
        'tr' => $this->t('Turkish'),
      ],
      '#default_value' => $this->getConfig()->get('settings.inflector_langcode') ?: 'en',
      '#required' => TRUE,
    ];

    $form['inflector']['inflector_singularize'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable singularize'),
      '#default_value' => $this->getConfig()->get('settings.inflector_singularize') ?: FALSE,
      '#description' => $this->t('
        Convert bundle names to singular form.<br>
        Eg Tags &rarr; Tag, termTagsItems() &rarr; termTags().'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->getConfig()
      ->set('settings.exclude_unpublished', $form_state->getValue('exclude_unpublished'))
      ->set('settings.expose_entity_ids', $form_state->getValue('expose_entity_ids'))
      ->set('settings.field_required_override', $form_state->getValue('field_required_override'))
      ->set('settings.simple_queries', $form_state->getValue('simple_queries'))
      ->set('settings.simple_unions', $form_state->getValue('simple_unions'))
      ->set('settings.svg_image', $form_state->getValue('svg_image', FALSE))
      ->set('settings.svg_filesize', $form_state->getValue('svg_filesize', 100))
      ->set('settings.inflector_langcode', $form_state->getValue('inflector_langcode'))
      ->set('settings.inflector_singularize', $form_state->getValue('inflector_singularize', FALSE))
      ->save();

    _graphql_compose_cache_flush();

    parent::submitForm($form, $form_state);
  }

}
