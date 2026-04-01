<?php

namespace Drupal\entity_mesh\Language;

use Drupal\Core\Language\LanguageInterface;
use Drupal\language\LanguageNegotiator;

/**
 * Language negotiator that only returns one fixed language.
 */
class StaticLanguageNegotiator extends LanguageNegotiator {


  /**
   * The language code that must be returned.
   *
   * @var \Drupal\Core\Language\LanguageInterface
   */
  protected LanguageInterface $language;

  /**
   * {@inheritdoc}
   */
  public function initializeType($type) {
    return [static::METHOD_ID => $this->language];
  }

  /**
   * Sets the language that will be always returned.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   Language that must be used.
   *
   * @return $this
   */
  public function setLanguage(LanguageInterface $language) {
    $this->language = $language;
    return $this;
  }

}
