<?php

declare(strict_types = 1);

namespace Drupal\views_geojson\Plugin\views\style;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use function count;
use function is_object;

/**
 * Style plugin to render view as GeoJSON code.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *     id="geojson",
 *     title=@Translation("GeoJSON"),
 *     theme="views_view_geojson",
 *     description=@Translation("Displays field data in GeoJSON data format."),
 *     display_types={"data"}
 * )
 */
class GeoJson extends StylePluginBase {

  /**
   * The definition.
   *
   * @var array
   */
  public $definition;

  /**
   * The serializer which serializes the views result.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Overrides \Drupal\views\Plugin\views\style\StylePluginBase::$usesFields.
   *
   * @var bool
   */
  protected $usesFields = TRUE;

  /**
   * Overrides Drupal\views\Plugin\views\style\StylePluginBase::$usesGrouping.
   *
   * @var bool
   */
  protected $usesGrouping = FALSE;

  /**
   * Overrides \Drupal\views\Plugin\views\style\StylePluginBase::$usesRowClass.
   *
   * @var bool
   */
  protected $usesRowClass = FALSE;

  /**
   * Overrides \Drupal\views\Plugin\views\style\StylePluginBase::$usesRowPlugin.
   *
   * @var bool
   */
  protected $usesRowPlugin = FALSE;

  /**
   * The serializer formats.
   *
   * @var array
   */
  private $formats;

  /**
   * GeoJson constructor.
   *
   * @param array $configuration
   *   The configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer.
   * @param array $serializer_formats
   *   The serializer formats.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, SerializerInterface $serializer, array $serializer_formats, ModuleHandlerInterface $module_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->definition = $plugin_definition + $configuration;
    $this->serializer = $serializer;
    $this->formats = ['json', 'html'];
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $fields = [];
    $fields_info = [];

    // Get list of fields in this view & flag available geodata fields.
    $handlers = $this->displayHandler->getHandlers('field');
    $field_definition = $this->displayHandler->getOption('fields');

    // Check for any fields, as the view needs them.
    if (empty($handlers)) {
      $form['error_markup'] = [
        '#value' => $this->t('You need to enable at least one field before you can configure your field settings'),
        '#prefix' => '<div class="error form-item description">',
        '#suffix' => '</div>',
      ];

      return;
    }

    // Go through fields, fill $fields and $fields_info arrays.
    foreach ($this->displayHandler->getHandlers('field') as $field_id => $handler) {
      $fields[$field_id] = $handler->definition['title'];
      $fields_info[$field_id]['type'] = $field_definition[$field_id]['type'];
    }

    // Default data source.
    $data_source_options = [
      'latlon' => $this->t('Other: Lat/Lon Point'),
      'geofield' => $this->t('Geofield'),
      'geolocation' => $this->t('Geolocation'),
      'wkt' => $this->t('WKT'),
    ];

    // Data Source options.
    $form['data_source'] = [
      '#type' => 'fieldset',
      '#tree' => TRUE,
      '#title' => $this->t('Data Source'),
    ];

    $form['data_source']['value'] = [
      '#type' => 'select',
      '#multiple' => FALSE,
      '#title' => $this->t('Map Data Sources'),
      '#description' => $this->t('Choose which sources of data that the map will provide features for.'),
      '#options' => $data_source_options,
      '#default_value' => $this->options['data_source']['value'],
    ];

    // Other Lat and Lon data sources.
    if (count($fields) > 0) {
      $form['data_source']['latitude'] = [
        '#type' => 'select',
        '#title' => $this->t('Latitude Field'),
        '#description' => $this->t('Choose a field for Latitude.  This should be a field that is a decimal or float value.'),
        '#options' => $fields,
        '#default_value' => $this->options['data_source']['latitude'],
        '#states' => [
          'visible' => [
            ':input[name="style_options[data_source][value]"]' => ['value' => 'latlon'],
          ],
        ],
      ];

      $form['data_source']['longitude'] = [
        '#type' => 'select',
        '#title' => $this->t('Longitude Field'),
        '#description' => $this->t('Choose a field for Longitude.  This should be a field that is a decimal or float value.'),
        '#options' => $fields,
        '#default_value' => $this->options['data_source']['longitude'],
        '#states' => [
          'visible' => [
            ':input[name="style_options[data_source][value]"]' => ['value' => 'latlon'],
          ],
        ],
      ];

      // Get Geofield-type fields.
      $geofield_fields = [];

      foreach ($fields as $field_id => $field) {
        // @todo We need to check if the field type is `geofield_default`. But
        // at the moment this information is missing from the array, due to a
        // bug with Geofield 8.x-1.x-dev. When the bug is fixed, we can add a
        // check here again.
        $geofield_fields[$field_id] = $field;
      }

      // Geofield.
      $form['data_source']['geofield'] = [
        '#type' => 'select',
        '#title' => $this->t('Geofield'),
        '#description' => $this->t("Choose a Geofield field. Any formatter will do; we'll access Geofield's underlying WKT format."),
        '#options' => $geofield_fields,
        '#default_value' => $this->options['data_source']['geofield'],
        '#states' => [
          'visible' => [
            ':input[name="style_options[data_source][value]"]' => ['value' => 'geofield'],
          ],
        ],
      ];

      // Get Geofield-type fields.
      $geolocation_fields = [];

      foreach ($fields as $field_id => $field) {
        // @todo need to limit to just geolocation fields.
        $geolocation_fields[$field_id] = $field;
      }

      // Geolocation.
      $form['data_source']['geolocation'] = [
        '#type' => 'select',
        '#title' => $this->t('Geolocation'),
        '#description' => $this->t("Choose a Geolocation field. Any formatter will do; we'll access Geolocation's underlying data storage."),
        '#options' => $geolocation_fields,
        '#default_value' => $this->options['data_source']['geolocation'],
        '#states' => [
          'visible' => [
            ':input[name="style_options[data_source][value]"]' => ['value' => 'geolocation'],
          ],
        ],
      ];

      // WKT.
      $form['data_source']['wkt'] = [
        '#type' => 'select',
        '#title' => $this->t('WKT'),
        '#description' => $this->t('Choose a WKT format field.'),
        '#options' => $fields,
        '#default_value' => $this->options['data_source']['wkt'],
        '#states' => [
          'visible' => [
            ':input[name="style_options[data_source][value]"]' => ['value' => 'wkt'],
          ],
        ],
      ];
    }

    $form['data_source']['id_field'] = [
      '#type' => 'select',
      '#title' => $this->t('ID Field'),
      '#description' => $this->t('Choose the field to appear as the Geojson feature ID.'),
      '#options' => array_merge(['' => ''], $fields),
      '#default_value' => $this->options['data_source']['id_field'],
    ];

    $form['data_source']['name_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Title Field'),
      '#description' => $this->t('Choose the field to appear as title on tooltips.'),
      '#options' => array_merge(['' => ''], $fields),
      '#default_value' => $this->options['data_source']['name_field'],
    ];

    $form['data_source']['description_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Description'),
      '#description' => $this->t('Choose the field or rendering method to appear as
          description on tooltips.'),
      '#required' => FALSE,
      '#options' => array_merge(['' => ''], $fields),
      '#default_value' => $this->options['data_source']['description_field'],
    ];

    // Attributes and variable styling description.
    $form['attributes'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Attributes and Styling'),
      '#description' => $this->t('Attributes are field data attached to each feature.  This can be used with styling to create Variable styling.'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    $form['jsonp_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('JSONP prefix'),
      '#default_value' => $this->options['jsonp_prefix'],
      '#description' => $this->t('If used the JSON output will be enclosed with parentheses and prefixed by this label, as in the JSONP format.'),
    ];

    // Make array of attributes.
    $variable_fields = [];
    // Add name and description.
    if (!empty($this->options['data_source']['name_field'])) {
      $variable_fields['name'] = '${name}';
    }

    if (!empty($this->options['data_source']['description_field'])) {
      $variable_fields['description'] = '${description}';
    }

    // Go through fields again to ID variable fields.
    // @todo is it necessary to call getHandlers twice or can we reuse data from
    // $fields?
    foreach ($this->displayHandler->getHandlers('field') as $field => $handler) {
      if (($field !== $this->options['data_source']['name_field']) && ($field !== $this->options['data_source']['description_field'])) {
        $variable_fields[$field] = '${' . $field . '}';
      }
    }

    $markup = $this->t('Fields added to this view will be attached to their respective feature, (point, line, polygon,) as attributes.
      These attributes can then be used to add variable styling to your themes. This is accomplished by using the %syntax
      syntax in the values for a style.  The following is a list of formatted variables that are currently available;
      these can be placed right in the style interface.', ['%syntax' => '${field_name}']);

    $form['attributes']['styling'][''] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#attributes' => [
        'class' => [
          'description',
        ],
      ],
      '#markup' => $markup,
    ];
    $form['attributes']['styling'][] = [
      '#theme' => 'item_list',
      '#items' => $variable_fields,
      '#attributes' => ['class' => ['description']],
    ];
    $form['attributes']['styling'][''] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#attributes' => [
        'class' => [
          'description',
        ],
      ],
      '#value' => $this->t('Please note that this does not apply to Grouped Displays.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('serializer'),
      $container->getParameter('serializer.formats'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormats() {
    return ['json', 'html'];
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $features = [
      'type' => 'FeatureCollection',
      'features' => [],
    ];

    // Get the excluded fields array, common for all rows.
    $excluded_fields = $this->getExcludedFields();

    // Render each row.
    foreach ($this->view->result as $i => $row) {
      $this->view->row_index = $i;

      if ($feature = $this->renderRow($row, $excluded_fields)) {
        $features['features'][] = $feature;
      }
    }

    $this->moduleHandler->alter('geojson_view', $features, $this->view);
    unset($this->view->row_index);

    if (!empty($this->view->live_preview)) {
      // Encode and pretty-print the JSON.
      $json = json_encode($features, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_PRETTY_PRINT);
    }
    else {
      // Render the collection to JSON using Drupal standard renderer.
      $json = Json::encode($features);
    }

    if (!empty($this->options['jsonp_prefix'])) {
      $json = $this->options['jsonp_prefix'] . "({$json})";
    }

    // Everything else returns output.
    return $json;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['data_source'] = [
      'contains' => [
        'value' => ['default' => 'asc'],
        'latitude' => ['default' => 0],
        'longitude' => ['default' => 0],
        'geofield' => ['default' => 0],
        'geolocation' => ['default' => 0],
        'wkt' => ['default' => 0],
        'id_field' => ['default' => 0],
        'name_field' => ['default' => 0],
        'description_field' => ['default' => 0],
      ],
    ];
    $options['attributes'] = ['default' => NULL, 'translatable' => FALSE];
    $options['jsonp_prefix'] = [
      'default' => NULL,
      'translatable' => FALSE,
    ];

    return $options;
  }

  /**
   * Retrieves the list of excluded fields due to style plugin configuration.
   *
   * @return array
   *   List of excluded fields.
   */
  protected function getExcludedFields() {
    $data_source = $this->options['data_source'];
    $excluded_fields = [
      $data_source['id_field'],
      $data_source['name_field'],
      $data_source['description_field'],
    ];

    switch ($data_source['value']) {
      case 'latlon':
        $excluded_fields[] = $data_source['latitude'];
        $excluded_fields[] = $data_source['longitude'];

        break;

      case 'geofield':
        $excluded_fields[] = $data_source['geofield'];

        break;

      case 'geolocation':
        $excluded_fields[] = $data_source['geolocation'];

        break;

      case 'wkt':
        $excluded_fields[] = $data_source['wkt'];

        break;
    }

    return array_combine($excluded_fields, $excluded_fields);
  }

  /**
   * Retrieves the description field value.
   *
   * @param \Drupal\views\ResultRow $row
   *   The result row.
   *
   * @return string
   *   The main field value.
   */
  protected function renderDescriptionField(ResultRow $row) {
    return $this->renderMainField($row, 'description_field');
  }

  /**
   * Retrieves the main fields values.
   *
   * @param \Drupal\views\ResultRow $row
   *   The result row.
   * @param string $field_name
   *   The main field name.
   *
   * @return string
   *   The main field value.
   */
  protected function renderMainField(ResultRow $row, $field_name) {
    if ($this->options['data_source'][$field_name]) {
      if (empty($this->view->field[$this->options['data_source'][$field_name]]->view->row_index)) {
        $this->view->field[$this->options['data_source'][$field_name]]->view->row_index = $row->index;
      }

      return $this->view->field[$this->options['data_source'][$field_name]]->advancedRender($row);
    }

    return '';
  }

  /**
   * Retrieves the ID field value.
   *
   * @param \Drupal\views\ResultRow $row
   *   The result row.
   *
   * @return string
   *   The ID field value.
   */
  protected function renderIdField(ResultRow $row) {
    return $this->renderMainField($row, 'id_field');
  }

  /**
   * Retrieves the name field value.
   *
   * @param \Drupal\views\ResultRow $row
   *   The result row.
   *
   * @return string
   *   The main field value.
   */
  protected function renderNameField(ResultRow $row) {
    return $this->renderMainField($row, 'name_field');
  }

  /**
   * Render views fields to GeoJSON.
   *
   * Takes each field from a row object and renders the field as determined by
   * the field's theme.
   *
   * @param \Drupal\views\ResultRow $row
   *   Row object.
   * @param array $excluded_fields
   *   Array containing field keys to be excluded.
   *
   * @throws \Exception
   *
   * @return array
   *   Array containing all the raw and rendered fields
   */
  protected function renderRow(ResultRow $row, array $excluded_fields) {
    $feature = ['type' => 'Feature'];
    $data_source = $this->options['data_source'];

    // Pre-render fields to handle those rewritten with tokens.
    foreach ($this->view->field as $field) {
      $field->advancedRender($row);
    }

    // Set the feature ID to the ID field chosen through the interface.
    // Only set the id if the field mapping is configured, and the value is
    // not empty. This prevents empty ID values in the Geojson document.
    $feature_id = $this->renderIdField($row);
    if (!empty($feature_id)) {
      $feature['id'] = $this->renderIdField($row);
    }

    switch ($data_source['value']) {
      case 'latlon':
        $latitude = (string) $this->view->field[$data_source['latitude']]->advancedRender($row);
        $longitude = (string) $this->view->field[$data_source['longitude']]->advancedRender($row);

        if (!empty($latitude) && !empty($longitude)) {
          $feature['geometry'] = [
            'type' => 'Point',
            'coordinates' => [(float) $longitude, (float) $latitude],
          ];
        }

        break;

      case 'geofield':
        // In case the set geofield data source field is there
        // (and not removed in the meanwhile).
        if ($this->view->field[$data_source['geofield']]) {
          $geofield = $this->view->style_plugin->getFieldValue($row->index, $data_source['geofield']);

          if (!empty($geofield)) {
            // In case of Multivalue Geofield.
            if (is_array($geofield)) {
              // Generate e GEOMETRYCOLLECTION union of all components,
              // if more than one, otherwise just use the first value.
              $geofield_components = count($geofield) > 1 ? "GEOMETRYCOLLECTION(" . implode(", ", $geofield) . ")" : $geofield[0];
              $geometry = \Drupal::getContainer()->get('geofield.geophp')->load($geofield_components);
            }
            // Otherwise generate the Geometry for the unique Geofield Value.
            else {
              $geometry = \Drupal::getContainer()->get('geofield.geophp')->load($geofield);
            }
            if (is_object($geometry)) {
              $feature['geometry'] = Json::decode($geometry->out('json'));
            }
          }
        }
        break;

      case 'geolocation':
        $geo_items = $this->view->field[$data_source['geolocation']]->getItems($row);

        if (!empty($geo_items[0]['raw'])) {
          $raw_geo_item = $geo_items[0]['raw'];
          $wkt = "POINT({$raw_geo_item->lng} {$raw_geo_item->lat})";
          $geometry = \geoPHP::load($wkt, 'wkt');

          if (is_object($geometry)) {
            $feature['geometry'] = Json::decode($geometry->out('json'));
          }
        }

        break;

      case 'wkt':
        $wkt = (string) $this->view->field[$data_source['wkt']]->advancedRender($row);

        if (!empty($wkt)) {
          try {
            $wkt = explode(',', $wkt);
            $geometry = \geoPHP::load($wkt, 'wkt');

            if (is_object($geometry)) {
              $feature['geometry'] = Json::decode($geometry->out('json'));
            }
          }
          catch (\Exception $e) {
            $type = $row->_entity->bundle();
            $id = $row->_entity->id();
            $title = $row->_entity->label();
            \Drupal::logger('views_geojson')->error("Invalid WKT on $type $id - $title : $wkt");
          }
        }

        break;
    }

    // Only add features with geometry data.
    if (empty($feature['geometry'])) {
      return [];
    }

    // Add the name and description attributes
    // as chosen through interface.
    $feature['properties']['name'] = $this->renderNameField($row);
    $feature['properties']['description'] = $this->renderDescriptionField($row);

    // Fill in attributes that are not:
    // - Coordinate fields,
    // - Name/description (already processed),
    // - Views "excluded" fields.
    foreach (array_keys($this->view->field) as $id) {
      $field = $this->view->field[$id];

      if (!isset($excluded_fields[$id]) && !($field->options['exclude'])) {
        // Allows you to customize the name of the property by setting a label
        // to the field.
        $key = empty($field->options['label']) ? $id : $field->options['label'];
        $value_rendered = $field->advancedRender($row);
        $feature['properties'][$key] = is_numeric($value_rendered) ? (float) $value_rendered : $value_rendered;
      }
    }

    return $feature;
  }

}
