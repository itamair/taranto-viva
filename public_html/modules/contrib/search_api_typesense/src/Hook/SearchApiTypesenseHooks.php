<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines a class containing Search API Typesense hooks.
 */
class SearchApiTypesenseHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('help')]
  public function help(string $route_name): string {
    switch ($route_name) {
      case 'search_api_typesense.collection.export':
        return '<p>' . $this->t(
            'Export and download the full collection data as a json file.',
          ) . '</p>';
    }

    return '';
  }

  /**
   * Implements hook_theme().
   *
   * @phpstan-return array<
   *   array-key,
   *   array{
   *     variables: array<array-key, mixed>
   *   }
   * >
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'search_api_typesense_search' => [
        'variables' => [
          'collection_render_parameters' => [],
          'facets' => [],
          'block_uuid' => NULL,
        ],
      ],
      'search_api_typesense_converse' => [
        'variables' => [
          'models' => [],
        ],
      ],
    ];
  }

  /**
   * Implements hook_form_alter().
   *
   * @phpstan-param array<array-key, mixed> $form
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('form_alter')]
  public function formAlter(
    array &$form,
    FormStateInterface $form_state,
    string $form_id,
  ): void {
    if ($form_id === 'search_api_index_fields') {
      // @phpstan-ignore-next-line
      $index = \Drupal::routeMatch()->getParameter('search_api_index');
      $link = ($index === NULL) ? 'Schema tab' : Link::createFromRoute(
        text: 'Schema tab',
        route_name: 'search_api_typesense.collection.schema',
        route_parameters: ['search_api_index' => $index->id()],
      )->toString();
      $message = $this->t('After selecting or updating fields here,
        you must go to the @link to review and submit the configuration.
        Indexing will not work until the Schema is properly configured and saved.', ['@link' => $link]);
      $form['warning'] = [
        '#markup' => "<div class='messages messages--warning'><h3>Warning</h3><p>$message</p></div>",
        '#weight' => -100,
      ];
    }
  }

}
