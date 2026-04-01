<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_views\Plugin\views\exposed_form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\exposed_form\ExposedFormPluginBase;

/**
 * Exposed form plugin, mostly a dummy to hide the submit and reset buttons.
 *
 * @ViewsExposedForm(
 *   id = "graphql",
 *   title = @Translation("GraphQL"),
 *   help = @Translation("GraphQL exposed forms"),
 *   display_types = {"graphql"},
 * )
 */
class GraphQL extends ExposedFormPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    hide($form['submit_button']);
    hide($form['reset_button']);
    hide($form['reset_button_label']);
    hide($form['exposed_sorts_label']);
    hide($form['sort_asc_label']);
    hide($form['sort_desc_label']);

  }

}
