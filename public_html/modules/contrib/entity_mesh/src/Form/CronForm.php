<?php

namespace Drupal\entity_mesh\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_mesh\TrackerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Entity Mesh cron settings for this site.
 */
class CronForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'entity_mesh.settings';

  /**
   * The tracker service.
   *
   * @var \Drupal\entity_mesh\TrackerInterface
   */
  protected $tracker;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\entity_mesh\TrackerInterface $tracker
   *   The tracker service.
   */
  final public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    TrackerInterface $tracker,
  ) {
    $this->typedConfigManager = $typedConfigManager;
    parent::__construct($config_factory, $typedConfigManager);
    $this->tracker = $tracker;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_mesh.tracker')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'entity_mesh_cron_settings';
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

    // Get tracker statistics.
    $pending_count = $this->tracker->getPendingCount();
    $failed_count = count($this->tracker->getFailedEntities());
    $tracked_count = $this->tracker->getTotalCount();

    $form['description'] = [
      '#type' => 'item',
      '#markup' => '<h2>' . $this->t('Entity Mesh Cron Configuration') . '</h2>',
    ];

    $form['info'] = [
      '#markup' => '<p>' . $this->t('Configure how Entity Mesh processes entities during cron runs. The cron processes pending entities from the tracker queue.') . '</p>',
    ];

    // Cron settings section.
    $form['cron_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Cron Settings'),
    ];

    $form['cron_settings']['cron_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable cron processing'),
      '#description' => $this->t('When enabled, Entity Mesh will process pending entities from the tracker queue during cron runs.'),
      '#default_value' => $config->get('cron_enabled') ?? TRUE,
    ];

    $form['cron_settings']['cron_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Entities to process per cron run'),
      '#description' => $this->t('Maximum number of entities to process during each cron run. This helps prevent timeouts on sites with many pending entities.'),
      '#default_value' => $config->get('cron_limit') ?? 50,
      '#min' => 1,
      '#max' => 9999,
      '#states' => [
        'visible' => [
          ':input[name="cron_enabled"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="cron_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config(self::SETTINGS);

    $config->set('cron_enabled', (bool) $form_state->getValue('cron_enabled'));

    // Only save cron_limit if cron is enabled.
    if ($form_state->getValue('cron_enabled')) {
      $config->set('cron_limit', (int) $form_state->getValue('cron_limit'));
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
