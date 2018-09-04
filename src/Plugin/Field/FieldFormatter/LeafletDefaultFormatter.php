<?php

namespace Drupal\leaflet\Plugin\Field\FieldFormatter;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Leaflet\LeafletService;
use Drupal\leaflet\LeafletSettingsElementsTrait;
use Drupal\Core\Utility\Token;
use Drupal\core\Render\Renderer;

/**
 * Plugin implementation of the 'leaflet_default' formatter.
 *
 * @FieldFormatter(
 *   id = "leaflet_formatter_default",
 *   label = @Translation("Leaflet Map"),
 *   field_types = {
 *     "geofield"
 *   }
 * )
 */
class LeafletDefaultFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  use LeafletSettingsElementsTrait;

  /**
   * Leaflet service.
   *
   * @var \Drupal\Leaflet\LeafletService
   */
  protected $leafletService;

  /**
   * The token service.
   *
   * @var \Drupal\core\Utility\Token
   */
  protected $token;

  /**
   * The renderer service.
   *
   * @var \Drupal\core\Render\Renderer
   */
  protected $renderer;

  /**
   * LeafletDefaultFormatter constructor.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\Leaflet\LeafletService $leaflet_service
   *   The Leaflet service.
   * @param \Drupal\core\Utility\Token $token
   *   The token service.
   * @param \Drupal\core\Render\Renderer $renderer
   *   The renderer service.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    LeafletService $leaflet_service,
    Token $token,
    Renderer $renderer
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->leafletService = $leaflet_service;
    $this->token = $token;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('leaflet.service'),
      $container->get('token'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'multiple_map' => 0,
      'leaflet_map' => 'OSM Mapnik',
      'height' => 400,
      'hide_empty_map' => 0,
      'popup' => FALSE,
      'popup_title' => '',
      'popup_text' => '',
      'map_position' => [
        'force' => 0,
        'center' => [
          'lat' => 0,
          'lon' => 0,
        ],
        'zoom' => 12,
        'minZoom' => 1,
        'maxZoom' => 18,
      ],
      'icon' => [
        'iconUrl' => '',
        'shadowUrl' => '',
        'iconSize' => ['x' => NULL, 'y' => NULL],
        'iconAnchor' => ['x' => NULL, 'y' => NULL],
        'shadowAnchor' => ['x' => NULL, 'y' => NULL],
        'popupAnchor' => ['x' => NULL, 'y' => NULL],
      ],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {

    // Get the Cardinality set for the Formatter Field.
    $field_cardinality = $this->fieldDefinition->getFieldStorageDefinition()
      ->getCardinality();

    $elements = parent::settingsForm($form, $form_state);
    $field_name = $this->fieldDefinition->getName();

    if ($field_cardinality !== 1) {
      $elements['multiple_map'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Multiple Maps'),
        '#description' => $this->t('Check this option if you want to render a single Map for every single Geo Point.'),
        '#default_value' => $this->getSetting('multiple_map'),
        '#return_value' => 1,
      ];
    }
    else {
      $elements['multiple_map'] = [
        '#type' => 'hidden',
        '#value' => 0,
      ];
    }

    $elements['popup'] = [
      '#title' => $this->t('Popup Infowindow'),
      '#description' => $this->t('Show a popup infowindow with the content Title.'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('popup'),
    ];

    $elements['popup_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Popup title'),
      '#description' => $this->t('The title is a tool tip that will be displayed when you click on the map marker.'),
      '#default_value' => $this->getSetting('popup_title'),
      '#states' => [
        'visible' => [
          'input[name="fields[' . $field_name . '][settings_edit_form][settings][popup]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $elements['popup_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Popup text'),
      '#description' => $this->t('This text will be displayed as a complementary text under the title. Leave blank if you do not wish to display it. See "REPLACEMENT PATTERNS" below for available replacements.'),
      '#default_value' => $this->getSetting('popup_text'),
      '#states' => [
        'visible' => [
          'input[name="fields[' . $field_name . '][settings_edit_form][settings][popup]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Generate the Leaflet Map General Settings.
    $this->generateMapGeneralSettings($elements, $this->getSettings());

    // Generate the Leaflet Map Position Form Element.
    $map_position_options = $this->getSetting('map_position');
    $elements['map_position'] = $this->generateMapPositionElement($map_position_options);

    // Generate Icon form element.
    $icon = $this->getSetting('icon');
    $elements['icon'] = $this->generateIconFormElement($icon);

    $elements['replacement_patterns'] = [
      '#type' => 'details',
      '#title' => 'Replacement patterns',
      '#description' => $this->t('The following replacement patterns are available for the "Popup title" and the "Popup text" settings.'),
    ];
    // Add the token UI from the token module if present.
    $elements['replacement_patterns']['token_help'] = [
      '#theme' => 'token_tree_link',
      '#prefix' => $this->t('<h4>Tokens:</h4>'),
      '#token_types' => [$this->fieldDefinition->getTargetEntityTypeId()],
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Leaflet Map: @map', ['@map' => $this->getSetting('leaflet_map')]);
    $summary[] = $this->t('Map height: @height px', ['@height' => $this->getSetting('height')]);
    if ($this->getSetting('popup_title')) {
      $summary[] = $this->t('Popup title: @title', ['@title' => $this->getSetting('popup_title')]);
    }
    if ($this->getSetting('popup_text')) {
      $summary[] = $this->t('Popup text: @text', ['@text' => $this->getSetting('popup_text')]);
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * This function is called from parent::view().
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {

    /* @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $items->getEntity();
    // Take the entity translation, if existing.
    /* @var \Drupal\Core\TypedData\TranslatableInterface $entity */
    if ($entity->hasTranslation($langcode)) {
      $entity = $entity->getTranslation($langcode);
    }

    // Sets/consider possibly existing previous Zoom settings.
    $this->setExistingZoomSettings();
    $settings = $this->getSettings();

    // Performs some preprocess on the leaflet map settings.
    $this->leafletService->preProcessMapSettings($settings);

    // Always render the map, even if we do not have any data.
    $map = leaflet_map_get_info($settings['leaflet_map']);

    // Set Map additional map Settings.
    $this->setAdditionalMapOptions($map, $settings);

    $features = [];
    foreach ($items as $delta => $item) {

      $points = $this->leafletService->leafletProcessGeofield($item->value);
      $feature = $points[0];

      // Eventually set the popup content.
      if ($settings['popup']) {
        // Get token context.
        $token_context = [
           'field' => $items,
           $this->fieldDefinition->getTargetEntityTypeId() => $items->getEntity(),
         ];
        // Construct the renderable array for popup title / text.
        $build = [];
        if ($this->getSetting('popup_title')) {
          $title = $this->token->replace($this->getSetting('popup_title'), $token_context);
          $build[] = [
            '#prefix' => '<div class="popup-title">',
            '#markup' => $title,
            '#suffix' => '</div>',
          ];
        }
        if ($this->getSetting('popup_text')) {
          $text = $this->token->replace($this->getSetting('popup_text'), $token_context);
          $build[] = [
            '#prefix' => '<div class="popup-text">',
            '#markup' => $text,
            '#suffix' => '</div>',
          ];
        }
        // We need a string for using it inside the popup.
        $build = $this->renderer->render($build);
        $popup = ($build ) ? $build : $entity->label();
        $feature['popup'] = $popup;
      }

      // Eventually set the custom icon.
      if (!empty($settings['icon']['iconUrl'])) {
        $feature['icon'] = $settings['icon'];
      }

      $features[] = $feature;
    }

    $results = [];
    if (!empty($settings['multiple_map'])) {
      foreach ($features as $feature) {
        $results[] = $this->leafletService->leafletRenderMap($map, [$feature], $settings['height'] . 'px');
      }
    }
    // Render the map, if we do have data or the hide option is unchecked.
    elseif (!empty($features) || empty($settings['hide_empty_map'])) {
      $results[] = $this->leafletService->leafletRenderMap($map, $features, $settings['height'] . 'px');
    }

    return $results;
  }

  /**
   * Sets possibly existing previous settings for the Zoom Form Element.
   */
  private function setExistingZoomSettings() {
    $settings = $this->getSettings();
    if (isset($settings['zoom'])) {
      $settings['map_position']['zoom'] = (int) $settings['zoom'];
      $settings['map_position']['minZoom'] = (int) $settings['minZoom'];
      $settings['map_position']['maxZoom'] = (int) $settings['maxZoom'];
      $this->setSettings($settings);
    }
  }

}
