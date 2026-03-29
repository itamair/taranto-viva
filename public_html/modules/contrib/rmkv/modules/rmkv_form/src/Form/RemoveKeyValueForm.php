<?php

namespace Drupal\rmkv_form\Form;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ProfileExtensionList;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements form of remove key/value.
 *
 * Remove system.schema key/value storage.
 */
final class RemoveKeyValueForm extends ConfigFormBase {

  /**
   * System schema of Key/value storage.
   */
  protected KeyValueStoreInterface $keyValueStore;

  /**
   * Constructs form of remove key/value.
   */
  public function __construct(
    protected ProfileExtensionList $profileExtensionList,
    protected ModuleHandlerInterface $moduleHandler,
    protected ThemeHandlerInterface $themeHandler,
    KeyValueFactoryInterface $keyvalue,
  ) {
    $this->keyValueStore = $keyvalue->get('system.schema');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('extension.list.profile'),
      $container->get('module_handler'),
      $container->get('theme_handler'),
      $container->get('keyvalue'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rmkv_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Form constructor.
    $form = parent::buildForm($form, $form_state);

    // Source text field.
    $form['system_schema_key_value_machine_name'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Machine name of the system.schema key/value storage for remove:'),
      '#machine_name' => [
        'exists' => [$this, 'validateMachineName'],
        'source' => [],
      ],
    ];

    // Submit button value.
    $form['actions']['submit']['#value'] = $this->t('Remove system.schema key/value storage');

    return $form;
  }

  /**
   * Validate machine name.
   *
   * Validation that the entered machine name does not
   * exist in the installed modules, themes and profiles.
   */
  public function validateMachineName(string $machine_name, array $element, FormStateInterface $form_state) {
    if (!$this->keyValueStore->has($machine_name)) {
      $form_state->setError($element, $this->t('Specified machine name "@machine_name" is not exists to the system.schema key/value storage.', [
        '@machine_name' => $machine_name,
      ]));
    }
    elseif ($this->profileExtensionList->exists($machine_name) || $this->moduleHandler->moduleExists($machine_name) || $this->themeHandler->themeExists($machine_name)) {
      $form_state->setError($element, $this->t('Cannot specify the machine name "@machine_name" of the installed profile, module or theme. Specify the machine name of the uninstalled profile, module or theme.', [
        '@machine_name' => $machine_name,
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get machine name of input value.
    $machine_name = $form_state->getValue('system_schema_key_value_machine_name');

    // Check of machine name.
    if ($this->keyValueStore->has($machine_name)) {
      if (!$this->profileExtensionList->exists($machine_name) && !$this->moduleHandler->moduleExists($machine_name) && !$this->themeHandler->themeExists($machine_name)) {
        $this->keyValueStore->delete($machine_name);
        $this->messenger()->addStatus($this->t('Succeeded in removing "@machine_name" from system.schema key/value storage.', [
          '@machine_name' => $machine_name,
        ]));
      }
      else {
        $this->messenger()->addError($this->t('Aborted remove of "@machine_name" from system.schema key/value storage, because specified machine name is already installed.', [
          '@machine_name' => $machine_name,
        ]));
      }
    }
    else {
      $this->messenger()->addWarning($this->t('Specified machine name "@machine_name" is not exists to the system.schema key/value storage. (* This message is displayed if it may have already been deleted.)', [
        '@machine_name' => $machine_name,
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'rmkv_form.form',
    ];
  }

}
