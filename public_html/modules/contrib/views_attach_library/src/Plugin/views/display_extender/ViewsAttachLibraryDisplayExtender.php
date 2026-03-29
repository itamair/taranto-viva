<?php

namespace Drupal\views_attach_library\Plugin\views\display_extender;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\views\Attribute\ViewsDisplayExtender;
use Drupal\views\Plugin\views\display_extender\DisplayExtenderPluginBase;

/**
 * Views Attach Library display extender plugin.
 *
 * @ingroup views_display_extender_plugins
 */
#[ViewsDisplayExtender(
  id: 'library_in_views_display_extender',
  title: new TranslatableMarkup('Library In Views Display Extender'),
  help: new TranslatableMarkup('Library In Views settings for this view.'),
  no_ui: TRUE
)]
class ViewsAttachLibraryDisplayExtender extends DisplayExtenderPluginBase {

  /**
   * Provide a form to edit options for this plugin.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    if ($form_state->get('section') == 'attach_library') {
      $description = [
        $this->t('Add library name in textfield , for example add <b>abc/xyz</b> where <b>abc</b> is module or theme name and <b>xyz</b> is library name.'),
        $this->t('<b>To add multiple libraries</b>, separate them with a <b>comma(,) separated.</b>'),
        [
          '#url' => Url::fromRoute('help.page', ['name' => 'views_attach_library']),
          '#title' => $this->t('For more info please read Readme.md'),
          '#type' => 'link',
          '#attributes' => [
            'target' => '_blank',
          ],
        ],
      ];
      $form['attach_library'] = [
        '#type' => 'textfield',
        '#title' => 'Add Libraries',
        '#description' => [
          '#theme' => 'item_list',
          '#list_type' => 'ul',
          '#items' => $description,
        ],
        '#default_value' => $this->options['attach_library'] ?? '',
      ];
    }
  }

  /**
   * Validate the options form.
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {

  }

  /**
   * Handle any special handling on the validate form.
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    if ($form_state->get('section') == 'attach_library') {
      $this->options['attach_library'] = $form_state->cleanValues()->getValue('attach_library');
    }
  }

  /**
   * Set up any variables on the view prior to execution.
   */
  public function preExecute() {

  }

  /**
   * Inject anything into the query that the display_extender handler needs.
   */
  public function query() {

  }

  /**
   * Provide the default summary for options in the views UI.
   *
   * This output is returned as an array.
   */
  public function optionsSummary(&$categories, &$options) {
    $categories['attach_library'] = [
      'title' => $this->t('Attach Library'),
      'column' => 'second',
    ];
    $options['attach_library'] = [
      'category' => 'attach_library',
      'title' => $this->t('Attach Library'),
      'value' => (empty($this->options['attach_library'])) ? $this->t('Add Library') : $this->t('Edit Library'),
    ];
  }

  /**
   * Lists defaultable sections and items contained in each section.
   */
  public function defaultableSections(&$sections, $section = NULL) {

  }

}
