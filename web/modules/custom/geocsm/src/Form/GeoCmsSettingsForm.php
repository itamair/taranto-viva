<?php

namespace Drupal\geocms\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Implements the Geofield311SettingsForm controller.
 *
 * @see \Drupal\Core\Form\FormBase
 */
class GeoCmsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('geocms.settings');

    $default_values = [];
    if (!empty($config->get('geocms_info_popup_settings.contents'))) {
      foreach ($config->get('geocms_info_popup_settings.contents') as $id) {
        $node = Node::load($id['target_id']);
        if ($node instanceof NodeInterface) {
          $default_values[] = Node::load($id['target_id']);
        }
      }
    }

    $form['#tree'] = TRUE;

    $form['geocms_info_popup_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('GeoCMS Info Popup Settings'),
    ];

    $form['geocms_info_popup_settings']['contents'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Content'),
      '#target_type' => 'node',
      '#tags' => TRUE,
      '#default_value' => $default_values,
      '#selection_settings' => [
        'target_bundles' => ['page'],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'geocms_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'geocms.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('geocms.settings');
    $config->set('geocms_info_popup_settings', $form_state->getValue('geocms_info_popup_settings'));
    $config->save();

    // Confirmation on form submission.
    $this->messenger()->addMessage($this->t('GeoCMS configurations have been saved.'));
  }

}
