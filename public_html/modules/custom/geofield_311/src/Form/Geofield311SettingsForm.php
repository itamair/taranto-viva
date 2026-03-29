<?php

namespace Drupal\geofield_311\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\LinkGeneratorInterface;

/**
 * Implements the Geofield311SettingsForm controller.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class Geofield311SettingsForm extends ConfigFormBase {

  /**
   * The Link generator Service.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected $link;

  /**
   * Geofield311SettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The Link Generator service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LinkGeneratorInterface $link_generator) {
    parent::__construct($config_factory);
    $this->link = $link_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('link_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('geofield_311.settings');

    $form['#tree'] = TRUE;

    $form['geojson_app_limit'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Geojson App Limit Settings'),
    ];

    $form['geojson_app_limit']['file'] = [
      '#field_prefix' => Url::fromUri('base:', ['absolute' => TRUE])->toString(),
      '#type' => 'textfield',
      '#title' => $this->t('Geojson file location'),
      '#default_value' => $config->get('geojson_app_limit.file') ?? '',
      '#description' => $this->t('Set the path to the Geojson File to be used'),
      '#element_validate' => [[get_class($this), 'geojsonFileValidate']],
    ];

    $form['geojson_app_limit']['stroke_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Geojson Stroke Color'),
      '#default_value' => $config->get('geojson_app_limit.stroke_color') ?? '#3355FF',
      '#description' => $this->t('Set the path to the Geojson stroke color in #hexadecimal format'),
    ];

    $form['geojson_app_limit']['stroke_weight'] = [
      '#title' => $this->t('Stroke Weight'),
      '#type' => 'number',
      '#min' => 0,
      '#max' => 10,
      '#default_value' => $config->get('geojson_app_limit.stroke_weight') ?? 3,
      '#description' => $this->t('Set the path to the Geojson stroke color in #hexadecimal format'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'geofield_311_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'geofield_311.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('geofield_311.settings');
    $config->set('geojson_app_limit', $form_state->getValue('geojson_app_limit'));
    $config->save();

    // Confirmation on form submission.
    $this->messenger()->addMessage($this->t('The Geofield 311 configurations have been saved.'));
  }

  /**
   * Geojson File Validate.
   *
   * Checks the existence of Geojson file in the defined location
   *
   */
  public static function geojsonFileValidate($element, FormStateInterface &$form_state) {
    $geojson_path = DRUPAL_ROOT . '/' . $element['#value'];
    if (!file_exists($geojson_path)) {
      $form_state->setError($element, t('The Geojson Path should point to an existing file.'));
    }
  }


}
