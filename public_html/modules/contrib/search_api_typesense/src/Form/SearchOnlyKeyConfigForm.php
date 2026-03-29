<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\ServerInterface;

/**
 * Configuration form for Typesense search-only API keys.
 */
class SearchOnlyKeyConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   *
   * @phpstan-return array<array-key, string>
   */
  protected function getEditableConfigNames(): array {
    return ['search_api_typesense.search_keys'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'search_api_typesense_search_only_key_config_form';
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<array-key, mixed> $form
   * @phpstan-param \Drupal\Core\Form\FormStateInterface $form_state
   * @phpstan-param \Drupal\search_api\ServerInterface|null $search_api_server
   *
   * @phpstan-return array<array-key, mixed>
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ServerInterface $search_api_server = NULL): array {
    $config = $this->config('search_api_typesense.search_keys');

    if (!$search_api_server instanceof ServerInterface) {
      $this->messenger()->addError($this->t('Invalid server parameter.'));
      return [];
    }

    $server_id = $search_api_server->id();
    $current_key = $config->get("servers.{$server_id}.search_only_key") ?? '';

    // Open details when key is missing, closed when it's set.
    $is_open = ($current_key === '');

    $form['search_key_config'] = [
      '#type' => 'details',
      '#title' => $this->t('Search-only API Key Configuration'),
      '#open' => $is_open,
      '#description' => $this->t('Configure a search-only API key for secure testing of search functionality.'),
    ];

    $form['search_key_config']['search_only_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search-only API key'),
      '#description' => $this->t('Enter the search-only API key for testing search functionality. This key should have only search permissions, not admin permissions.'),
      '#default_value' => $current_key,
      '#size' => 60,
      '#maxlength' => 128,
    ];

    if ($is_open) {
      $form['search_key_config']['warning'] = [
        '#type' => 'markup',
        '#markup' => '<div class="messages messages--warning">' .
        $this->t('<strong>Warning:</strong> Make sure to use a search-only API key here, not your admin one. Search-only keys provide limited permissions and are safer for querying the Typesense server.') .
        '</div>',
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<array-key, mixed> $form
   * @phpstan-param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->configFactory()->getEditable('search_api_typesense.search_keys');

    // Get the server from the form build args.
    $build_info = $form_state->getBuildInfo();
    $server = $build_info['args'][0] ?? NULL;

    if (!$server instanceof ServerInterface) {
      $this->messenger()->addError($this->t('Invalid server parameter.'));
      return;
    }

    $server_id = $server->id();
    $search_only_key = $form_state->getValue(['search_only_key']);

    if ($search_only_key !== '' && $search_only_key !== NULL) {
      $config->set("servers.{$server_id}.search_only_key", $search_only_key);
    }
    else {
      $config->clear("servers.{$server_id}.search_only_key");
    }

    $config->save();

    $this->messenger()->addStatus($this->t('The search-only API key configuration has been saved.'));
    parent::submitForm($form, $form_state);
  }

}
