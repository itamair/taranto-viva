<?php

namespace Drupal\custom_layouts\Plugin\paragraphs\Behavior;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;

/**
 * Provides a plugin for defining the deck title as <h1>, <h2> or <h3>.
 *
 * @ParagraphsBehavior(
 *   id = "title",
 *   label = @Translation("Title"),
 *   description = @Translation("Choose h1, h2 or h3 for the Title component."),
 *   weight = -2
 * )
 */
class TitleBehavior extends ParagraphsBehaviorBase {

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $form['title_behavior'] = [
      '#type' => 'select',
      '#title' => $this->t('Select title format'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'title_behavior', FALSE),
      '#description' => $this->t('Choose h1, h2 or h3 for the Title component.'),
      '#options' => $this->getOptions(),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraph, EntityViewDisplayInterface $display, $view_mode) {
    if ($title_behavior = $paragraph->getBehaviorSetting($this->getPluginId(), 'title_behavior')) {
      $build['#title_behavior'] = $title_behavior;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(ParagraphsType $paragraphs_type) {
    $allowlist_paragraph_types = [
      'title',
    ];

    if (in_array($paragraphs_type->id(), $allowlist_paragraph_types)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Default options used in the behavior widget.
   *
   * @return array
   *   List of behavior options
   */
  public function getOptions(): array {
    return [
      'h1' => 'h1',
      'h2' => 'h2',
      'h3' => 'h3',
    ];
  }

}
