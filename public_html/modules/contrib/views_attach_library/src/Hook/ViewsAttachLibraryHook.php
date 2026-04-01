<?php

namespace Drupal\views_attach_library\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views\ViewExecutable;

/**
 * Hook implementations for Views Attach Library.
 */
class ViewsAttachLibraryHook {
  use StringTranslationTrait;

  /**
   * Implements hook_views_pre_render().
   */
  #[Hook('views_pre_render')]
  public function viewsPreRender(ViewExecutable $view) {
    $current_display = $view->current_display;
    $view_config = $view->storage->getDisplay($current_display);
    $extender = $view_config['display_options']['display_extenders']['library_in_views_display_extender'] ?? [];
    if (!empty($extender)) {
      $attach_library = $extender['attach_library'] ?? '';
      if (!empty(trim($attach_library))) {
        $libraries = explode(',', trim($attach_library));
        foreach ($libraries as $library) {
          // Attach library to view.
          if (!empty(trim($library))) {
            $view->element['#attached']['library'][] = trim($library);
          }
        }
      }
    }
  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'help.page.views_attach_library':
        $text = file_get_contents(__DIR__ . '/../../README.md');
        if (!\Drupal::moduleHandler()->moduleExists('markdown')) {
          return '<pre>' . Html::escape($text) . '</pre>';
        }
        else {
          // Use the Markdown filter to render the README.
          $filter_manager = \Drupal::service('plugin.manager.filter');
          $settings = \Drupal::configFactory()->get('markdown.settings')->getRawData();
          $config = ['settings' => $settings];
          $filter = $filter_manager->createInstance('markdown', $config);
          return $filter->process($text, 'en');
        }

      default:
        break;
    }
    return FALSE;
  }

}
