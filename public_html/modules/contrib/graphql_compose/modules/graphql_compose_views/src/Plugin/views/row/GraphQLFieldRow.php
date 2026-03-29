<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_views\Plugin\views\row;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldHandlerInterface;
use Drupal\views\Plugin\views\row\RowPluginBase;
use function Symfony\Component\String\u;

/**
 * Plugin which displays fields as raw data.
 *
 * @ViewsRow(
 *   id = "graphql_field",
 *   title = @Translation("Fields"),
 *   help = @Translation("Use fields as row data."),
 *   display_types = {"graphql"},
 * )
 */
class GraphQLFieldRow extends RowPluginBase {

  /**
   * {@inheritdoc}
   */
  protected $usesFields = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['field_options'] = ['default' => []];

    return $options;
  }

  /**
   * Returns the field options.
   *
   * @return array
   *   The field options.
   */
  protected function getFieldOptions(): array {
    return (array) ($this->options['field_options'] ?? []);
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $fields = $this->view->display_handler->getOption('fields') ?: [];

    // Filter out the excluded fields.
    $fields = array_filter(
      $fields,
      fn(array $field) => empty($field['exclude'])
    );

    $form['field_options'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Field'),
        $this->t('Alias'),
        $this->t('Raw'),
        $this->t('Nullable'),
        $this->t('Type'),
      ],
      '#empty' => $this->t('You have no fields. Add some to your view.'),
      '#tree' => TRUE,
    ];

    foreach ($fields as $id => $field) {
      $form['field_options'][$id]['field'] = [
        '#markup' => $id,
      ];

      $form['field_options'][$id]['alias'] = [
        '#title' => $this->t('Alias for @id', ['@id' => $id]),
        '#title_display' => 'invisible',
        '#type' => 'textfield',
        '#default_value' => $this->getAlias($id, FALSE),
        '#element_validate' => [[$this, 'validateAlias']],
      ];

      $form['field_options'][$id]['raw_output'] = [
        '#title' => $this->t('Raw output for @id', ['@id' => $id]),
        '#title_display' => 'invisible',
        '#type' => 'checkbox',
        '#default_value' => $this->isRaw($id),
      ];

      $form['field_options'][$id]['nullable'] = [
        '#title' => $this->t('Nullable output for @id', ['@id' => $id]),
        '#title_display' => 'invisible',
        '#type' => 'checkbox',
        '#default_value' => $this->isNullable($id),
      ];

      $form['field_options'][$id]['type'] = [
        '#type' => 'select',
        '#options' => [
          'String' => $this->t('String'),
          'Int' => $this->t('Int'),
          'Float' => $this->t('Float'),
          'Boolean' => $this->t('Boolean'),
          'Scalar' => $this->t('Custom Scalar'),
        ],
        '#default_value' => $this->getType($id),
      ];

    }
  }

  /**
   * Form element validation handler.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateAlias(array $element, FormStateInterface $form_state) {
    $value = $element['#value'] ?: '';
    if ($value && !preg_match('/^[a-z]([A-Za-z0-9]+)?$/', $value)) {
      $message = $this->t('@name must start with a lowercase letter and contain only letters and numbers.', [
        '@name' => $element['#title'] ?? 'Field name',
      ]);
      $form_state->setError($element, $message);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    $options = $form_state->getValue(['row_options', 'field_options']) ?: [];

    $aliases = array_filter(array_column($options, 'alias'));
    $unique_aliases = array_unique($aliases);

    if (count($aliases) !== count($unique_aliases)) {
      $form_state->setErrorByName('field_options', $this->t('All field aliases must be unique.'));
    }
  }

  /**
   * Return an alias for a field ID, as set in the options form.
   *
   * @param string $id
   *   The field id to lookup an alias for.
   * @param bool $with_default
   *   Whether to return the default alias if none is set.
   *
   * @return string
   *   The matches user entered alias, or null when an empty.
   */
  public function getAlias(string $id, bool $with_default = TRUE): string {
    $options = $this->getFieldOptions();
    $alias = $options[$id]['alias'] ?? NULL;
    $alias = $alias ? trim($alias) : NULL;

    if ($with_default && empty($alias)) {
      $alias = $this->getDefaultAlias($id);
    }

    return $alias ?: '';
  }

  /**
   * Return a GraphQL field type, as set in the options form.
   *
   * @param string $id
   *   The field id to lookup a type for.
   *
   * @return string
   *   The matches user entered type, or String.
   */
  public function getType(string $id): string {
    $options = $this->getFieldOptions();

    return $options[$id]['type'] ?? 'String';
  }

  /**
   * Checks the field should return raw value (without a render).
   *
   * @param string $id
   *   Field name.
   *
   * @return bool
   *   TRUE if the field is set to raw.
   */
  public function isRaw(string $id): bool {
    $options = $this->getFieldOptions();

    return (bool) ($options[$id]['raw_output'] ?? FALSE);
  }

  /**
   * Checks the field is nullable.
   *
   * @param string $id
   *   Field name.
   *
   * @return bool
   *   TRUE if the field can be set to null.
   */
  public function isNullable(string $id): bool {
    $options = $this->getFieldOptions();

    return (bool) ($options[$id]['nullable'] ?? FALSE);
  }

  /**
   * Returns a default field alias for a given field ID.
   *
   * @param string $id
   *   The field ID to generate an alias for.
   *
   * @return string
   *   The default alias for the field ID.
   */
  protected function getDefaultAlias(string $id): string {
    return u($id)
      ->trimPrefix('field_')
      ->camel()
      ->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function render($row) {
    $output = [];

    /** @var array<string,FieldHandlerInterface> $fields */
    $fields = $this->view->field ?: [];

    // Filter out excluded fields.
    $fields = array_filter(
      $fields,
      fn (FieldHandlerInterface $field) => empty($field->options['exclude'])
    );

    foreach ($fields as $id => $field) {
      // Get the field type.
      $field_type = $this->getType($id);

      if ($this->isRaw($id)) {
        $value = $field->getValue($row);
      }
      else {
        // Renders a field using "advanced" settings.
        // This also wraps safe markup in MarkupInterface.
        $markup = $field->advancedRender($row);
        // Post render to support un-cacheable fields.
        $field->postRender($row, $markup);
        $value = $field->last_render;
      }

      // If the value is a MarkupInterface, convert it to a string.
      $value = $value instanceof MarkupInterface ? (string) $value : $value;

      // Prep for conversion. Strip and trim Int/Float/Boolean strings.
      if (is_string($value) && in_array($field_type, ['Int', 'Float', 'Boolean'])) {
        $value = trim(strip_tags($value));
      }

      // Convert the value to the appropriate type.
      switch ($field_type) {
        case 'String':
          $value = Xss::filter((string) $value);
          break;

        case 'Int':
          $value = (int) $value;
          break;

        case 'Float':
          $value = (float) filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
          break;

        case 'Boolean':
          $truthy = [
            (string) $this->t('yes'),
            (string) $this->t('true'),
            (string) $this->t('on'),
            (string) $this->t('enabled'),
            'y',
            'yes',
            'true',
            'on',
            'enabled',
            '1',
            1,
            TRUE,
          ];

          $value = is_string($value) ? strtolower($value) : $value;
          $value = in_array($value, $truthy, TRUE);
          break;

        case 'Scalar':
          if ($this->isRaw($id) && is_string($value)) {
            try {
              // We can attempt to convert from JSON.
              $json = Json::decode($value);
              // Because false/null are valid JSON values
              // we should check for errors. If none, great!
              $value = json_last_error() ? $value : $json;
            }
            catch (InvalidDataTypeException) {
              // Couldn't convert to JSON.
              // Don't change the value further.
            }
          }
          elseif (is_string($value)) {
            $value = Xss::filter($value);
          }
          break;

        default:
          throw new \InvalidArgumentException(
            sprintf('Invalid field type "%s" for field "%s".', $field_type, $id)
          );
      }

      if ($this->isNullable($id) && empty($value)) {
        $value = NULL;
      }

      $output[$this->getAlias($id)] = $value;
    }

    return $output;
  }

}
