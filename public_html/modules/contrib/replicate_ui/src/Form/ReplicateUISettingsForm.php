<?php

namespace Drupal\replicate_ui\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ReplicateUISettingsForm extends ConfigFormBase {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The route builder.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected $routerBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $form = parent::create($container);
    $form->entityTypeManager = $container->get('entity_type.manager');
    $form->routerBuilder = $container->get('router.builder');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['replicate_ui.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'replicate_ui__settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $content_entity_types = array_filter($this->entityTypeManager->getDefinitions(), function (EntityTypeInterface $entity_type) {
      return $entity_type instanceof ContentEntityTypeInterface;
    });
    $form['entity_types']  = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Replicate entity types'),
      '#description' => $this->t('Enable replicate for the following entity types'),
      '#options' => array_map(function (EntityTypeInterface $entity_type) {
        return $entity_type->getLabel();
      }, $content_entity_types),
      '#default_value' => $this->config('replicate_ui.settings')->get('entity_types'),
    ];

    $form['check_edit_access'] = [
      '#type' => 'checkbox',
      '#title' => t('Check original entity edit access'),
      '#description' => $this->t('Disable replicating entities which the user is not allowed to edit'),
      '#default_value' => $this->config('replicate_ui.settings')->get('check_edit_access'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('replicate_ui.settings')
      ->set('entity_types', array_values(array_filter($form_state->getValue('entity_types'))))
      ->set('check_edit_access', $form_state->getValue('check_edit_access'))
      ->save();
    // @todo This should be done through a config save subscriber and it should
    // also invalidate the render/local tasks cache.
    $this->routerBuilder->setRebuildNeeded();
    Cache::invalidateTags(['entity_types', 'views_data']);
  }

}
