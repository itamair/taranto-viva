<?php

namespace Drupal\mcp_studio\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for deleting a Studio tool.
 */
class ToolDeleteForm extends ConfirmFormBase {


  /**
   * The tool ID.
   *
   * @var int
   */
  protected $toolId;

  /**
   * The tool data.
   *
   * @var array
   */
  protected $tool;

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
    return 'mcp_studio_tool_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the tool %name?', ['%name' => $this->tool['name']]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('mcp_studio.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $tool_id = NULL) {
    $this->toolId = $tool_id;

    $config = $this->configFactory()->get('mcp_studio.settings');
    $tools = $config->get('tools') ?? [];

    if (!isset($tools[$tool_id])) {
      $this->messenger()->addError($this->t('Tool not found.'));
      return $this->redirect('mcp_studio.settings');
    }

    $this->tool = $tools[$tool_id];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('mcp_studio.settings');
    $tools = $config->get('tools') ?? [];

    unset($tools[$this->toolId]);
    $tools = array_values($tools);

    $config->set('tools', $tools)->save();

    $this->messenger()->addStatus($this->t('Tool %name has been deleted.', ['%name' => $this->tool['name']]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
