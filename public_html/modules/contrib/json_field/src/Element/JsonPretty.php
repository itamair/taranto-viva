<?php

namespace Drupal\json_field\Element;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Element\RenderElementBase;

/**
 * Provides a JSON pretty render element.
 *
 * @RenderElement("json_pretty")
 */
class JsonPretty extends RenderElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    return [
      '#json' => NULL,
      '#pre_render' => [[static::class, 'preRenderJsonPretty']],
    ];
  }

  /**
   * Pre-render callback: Renders a JSON text element into #markup.
   */
  public static function preRenderJsonPretty(array $element): array {
    return [
      '#markup' => new FormattableMarkup(
        '<div class="json-field-pretty">' . static::formatJson($element['#json']) . '</div>', [],
      ),
      '#attached' => ['library' => ['json_field/json_field.pretty']],
    ];
  }

  /**
   * Formats using nested HTML lists.
   *
   * @param mixed $json
   *   The JSON data to display.
   *
   * @return string
   *   The JSON data formatted as nested HTML lists.
   */
  protected static function formatJson($json): string {
    $markup = '';
    if (is_array($json)) {
      if (empty($json)) {
        $markup .= '[empty array]';
      }
      else {
        $markup .= '<ul>';
        foreach ($json as $value) {
          $markup .= '<li>' . static::formatJson($value) . '</li>';
        }
        $markup .= '</ul>';
      }
    }
    elseif (is_object($json)) {
      $vars = get_object_vars($json);
      if (empty($vars)) {
        $markup .= '{empty object}';
      }
      else {
        $markup .= '<dl>';
        foreach ($json as $key => $value) {
          $markup .= '<dt>' . $key . '</dt><dd>' . static::formatJson($value) . '</dd>';
        }
        $markup .= '</dl>';
      }
    }
    elseif (is_null($json)) {
      $markup .= 'null';
    }
    elseif (is_bool($json)) {
      $markup .= $json ? 'true' : 'false';
    }
    elseif ($json === '') {
      $markup .= '""';
    }
    else {
      $markup .= Html::escape($json);
    }
    return $markup;
  }

}
