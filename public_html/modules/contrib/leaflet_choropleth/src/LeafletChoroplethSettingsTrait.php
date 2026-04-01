<?php

declare(strict_types=1);

namespace Drupal\leaflet_choropleth;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\leaflet\LeafletSettingsElementsTrait;

/**
 * Provides elements for choropleth map settings.
 */
trait LeafletChoroplethSettingsTrait {

  use StringTranslationTrait;
  use LeafletSettingsElementsTrait;

  /**
   * Gets the default choropleth settings.
   *
   * @return array
   *   The default settings.
   */
  public static function getDefaultChoroplethSettings(): array {
    return [
      'enabled' => TRUE,
      'data_field' => '',
      'classification' => 'quantile',
      'color_scale' => 'sequential_blues',
      'classes' => 5,
      'reverse' => FALSE,
      'path' => '{"color":"#26446e","opacity":"1.0","stroke":true,"weight":2,"fillOpacity":"0.8"}',
      'legend' => [
        'legend_control_enabled' => TRUE,
        'position' => 'topright',
        'start_collapsed' => FALSE,
        'legend_collapsed_label' => 'Legend',
        'title' => '',
        'subtitle' => [
          'value' => '',
          'format' => 'full_html',
        ],
        'notes' => [
          'value' => '',
          'format' => 'full_html',
        ],
        'notes_after' => TRUE,
        'decimals' => 1,
        'item_format' => '%min - %max | %value',
        'values' => [],
      ],
    ];
  }

  /**
   * Adds choropleth settings form elements.
   *
   * @param array $form
   *   The form array to add elements to.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $settings
   *   The current settings.
   * @param array $fields
   *   Available fields for data source.
   */
  public function setChoroplethMapSettings(array &$form, FormStateInterface $form_state, array $settings, array $fields): void {
    $settings += self::getDefaultChoroplethSettings();

    // Get the plugin managers from the container.
    $color_scale_manager = \Drupal::service('plugin.manager.leaflet_choropleth_color_scale');
    $classification_manager = \Drupal::service('plugin.manager.leaflet_choropleth_classification');

    $form['choropleth'] = [
      '#type' => 'details',
      '#title' => $this->t('Choropleth Map Settings'),
      '#open' => $settings['enabled'],
      '#tree' => TRUE,
    ];

    $form['choropleth']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Choropleth Map'),
      '#description' => $this->t('Display regions colored according to data values.'),
      '#default_value' => $settings['enabled'],
      '#return_value' => 1,
    ];

    $form['choropleth']['data_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Data Source Field'),
      '#description' => $this->t('Select the field containing numerical data values to use for coloring regions.'),
      '#options' => array_merge(['' => $this->t('- None -')], $fields),
      '#default_value' => $settings['data_field'],
      '#states' => [
        'visible' => [
          ':input[name="style_options[choropleth][enabled]"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="style_options[choropleth][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $classification_methods = $classification_manager->getOptions();

    $form['choropleth']['classification'] = [
      '#type' => 'select',
      '#title' => $this->t('Classification Method'),
      '#description' => $this->t('How data ranges should be divided into classes.'),
      '#options' => $classification_methods['labels'],
      '#default_value' => $settings['classification'],
      '#states' => [
        'visible' => [
          ':input[name="style_options[choropleth][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Add List of available Classification (Plugins) Methods Descriptions.
    $form['choropleth']['classification']['#description'] .= '<div>' . $this->t('Available Classification Methods:') . '<ul>';
    foreach ($classification_methods['labels'] as $k => $classification_method) {
      $form['choropleth']['classification']['#description'] .= '<li>' . $classification_methods['labels'][$k] . ': ' . $classification_methods['descriptions'][$k] . '</li>';
    }

    $form['choropleth']['color_scale'] = [
      '#type' => 'select',
      '#title' => $this->t('Color Scale'),
      '#description' => $this->t('Select color scale for the choropleth map.'),
      '#options' => $color_scale_manager->getOptions(),
      '#default_value' => $settings['color_scale'],
      '#states' => [
        'visible' => [
          ':input[name="style_options[choropleth][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['choropleth']['classes'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of Classes'),
      '#description' => $this->t('Number of color classes to divide the data into.'),
      '#min' => 3,
      '#max' => 9,
      '#default_value' => $settings['classes'],
      '#states' => [
        'visible' => [
          ':input[name="style_options[choropleth][enabled]"]' => ['checked' => TRUE],
        ],
      ],
      '#ajax' => [
        'callback' => '\Drupal\leaflet_choropleth\Plugin\views\style\LeafletChoroplethMap::promptCallback',
        'event' => 'change',
      ],
    ];

    $form['choropleth']['reverse'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reverse Color Scale'),
      '#description' => $this->t('Reverse the order of colors in the scale.'),
      '#default_value' => $settings['reverse'],
      '#return_value' => 1,
      '#states' => [
        'visible' => [
          ':input[name="style_options[choropleth][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $path_description = $this->t('Set here options that will be specifically applied to the rendering of Choropleth Path Geometries (Polygons & Multipolygons).<br>Refer to the @polygons_documentation.<br>Notes: <ul><li><b>This is overriding any Leaflet Path style defined (only for Choropleth Path Geometries).</b></li><li><b>The fillColor property is (anyway) being driven by the selected Color Scale.</b></li></ul>@token_replacement_disclaimer<br>Single Token or Replacement containing the whole Json specification are supported).', [
      '@polygons_documentation' => $this->link->generate($this->t('Leaflet Path Documentation'), Url::fromUri('https://leafletjs.com/reference.html#path', [
        'absolute' => TRUE,
        'attributes' => ['target' => 'blank'],
      ])),
      '@token_replacement_disclaimer' => $this->getTokenReplacementDisclaimer(),
    ]);

    $form['choropleth']['path'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Choropleth Path Geometries Options'),
      '#rows' => 3,
      '#description' => $path_description,
      '#default_value' => $settings['path'],
      '#placeholder' => $this::getDefaultChoroplethSettings()['path'],
      '#element_validate' => [[get_class($this), 'jsonValidate']],
    ];

    $this->setChoroplethLegendSettings($form, $form_state, $settings);
  }

  /**
   * Set the legend settings form elements.
   *
   * @param array $form
   *   The form array to add elements to.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $settings
   *   The current settings.
   */
  protected function setChoroplethLegendSettings(array &$form, FormStateInterface $form_state, array $settings): void {
    $form['choropleth']['legend'] = [
      '#type' => 'details',
      '#title' => $this->t('Legend Settings'),
      '#open' => TRUE,
    ];

    $form['choropleth']['legend']['legend_control_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Legend Control enabled'),
      '#description' => $this->t('Automatically show a Legend Control inside the Leaflet Choropleth Map.'),
      '#default_value' => $settings['legend']['legend_control_enabled'],
      '#return_value' => 1,
    ];

    $form['choropleth']['legend']['position'] = [
      '#type' => 'select',
      '#title' => $this->t('Legend Control Position'),
      '#description' => $this->t('Position of the Leaflet Legend Control on the map.'),
      '#options' => [
        'topleft' => $this->t('Top Left'),
        'topright' => $this->t('Top Right'),
        'bottomleft' => $this->t('Bottom Left'),
        'bottomright' => $this->t('Bottom Right'),
      ],
      '#default_value' => $settings['legend']['position'],
      '#states' => [
        'visible' => [
          ':input[name="style_options[choropleth][legend][legend_control_enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['choropleth']['legend']['start_collapsed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Start Legend Collapsed'),
      '#description' => $this->t('Start the legend in a collapsed state to reduce prominence on smaller screens. Users can click to expand.'),
      '#default_value' => $settings['legend']['start_collapsed'],
      '#return_value' => 1,
      '#states' => [
        'visible' => [
          ':input[name="style_options[choropleth][legend][legend_control_enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['choropleth']['legend']['legend_collapsed_label'] = [
      '#type' => 'textfield',
      '#size' => 20,
      '#title' => $this->t('Legend Collapsed Label'),
      '#description' => $this->t('Label to show when the Legend control is collapsed.'),
      '#default_value' => $settings['legend']['legend_collapsed_label'],
      '#placeholder' => self::getDefaultChoroplethSettings()['legend']['legend_collapsed_label'],
    ];

    $form['choropleth']['legend']['title'] = [
      '#type' => 'textfield',
      '#size' => 100,
      '#title' => $this->t('Legend Title'),
      '#description' => $this->t('Title for the legend. Leave empty for no title.'),
      '#default_value' => $settings['legend']['title'],
    ];

    $form['choropleth']['legend']['subtitle'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Legend Sub Title / Caption'),
      '#description' => $this->t('Sub Title / Caption for the legend. Leave empty for no subtitle.'),
      '#default_value' => $settings['legend']['subtitle']['value'] ?? '',
      '#format' => $settings['legend']['subtitle']['format'] ?? 'full_html',
      '#rows' => 2,
    ];

    $form['choropleth']['legend']['notes'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Notes'),
      '#description' => $this->t('Notes for the legend.'),
      '#default_value' => $settings['legend']['notes']['value'] ?? '',
      '#description_display' => 'before',
      '#format' => $settings['legend']['notes']['format'] ?? 'full_html',
      '#rows' => 3,
    ];

    $form['choropleth']['legend']['notes_after'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Place Notes position after Legend'),
      '#description' => $this->t('Check this to set the Note position After the legend, instead of Before.'),
      '#default_value' => $settings['legend']['notes_after'] ?? TRUE,
      '#return_value' => 1,
    ];

    $form['choropleth']['legend']['decimals'] = [
      '#type' => 'number',
      '#title' => $this->t('Item Decimal Places'),
      '#description' => $this->t('Number of decimal places to show for each Legend Item value.'),
      '#min' => 0,
      '#max' => 6,
      '#default_value' => $settings['legend']['decimals'],
    ];

    $form['choropleth']['legend']['item_format'] = [
      '#type' => 'textarea',
      '#rows' => 1,
      '#title' => $this->t('Items Format'),
      '#description' => $this->t('Customise each Legend Item Format:
<br>%min and %max (and %value) placeholders are being replaced by data "min" and
"max" (and custom "value") values</br><b>Note:</b> %value placeholder can be used for
categorical classification. Legend %value(s)can be set via the Legend %values
settings, and altered via leaflet_choropleth_color_scale_alter hook
(@see leaflet_choropleth.api.php file).'),
      '#default_value' => $settings['legend']['item_format'],
      '#placeholder' => self::getDefaultChoroplethSettings()['legend']['item_format'],
    ];

    $user_input = $form_state->getUserInput();
    $classes_number = $user_input['style_options']['choropleth']['classes'] ?? $form["choropleth"]["classes"]["#default_value"];
    if ($classes_number > 0) {
      $form['choropleth']['legend']['values'] = [
        '#prefix' => '<div id="choropleth-legend-values-wrapper">',
        '#open' => TRUE,
        '#suffix' => '</div>',
        "#type" => "details",
        "#title" => $this->t('Legend %value(s)  (for selected @classes_number classes)', ['@classes_number' => $classes_number]),
        "#description" => $this->t('Legend %value(s) for the Legend Items
Format. <b>Note:</b> Number of %values dynamically adjusts to the chosen number
of classes'),
        "#description_display" => "before",
      ];
      for ($i = 1; $i <= $classes_number; $i++) {
        $form['choropleth']['legend']['values'][$i] = [
          '#type' => 'textfield',
          '#title' => $this->t('Value @i', ['@i' => $i]),
          '#description' => $this->t('Input the Legend Value for item / interval n. @i', ['@i' => $i]),
          '#default_value' => $settings['legend']['values'][$i] ?? '',
        ];
      }
    }
  }

}
