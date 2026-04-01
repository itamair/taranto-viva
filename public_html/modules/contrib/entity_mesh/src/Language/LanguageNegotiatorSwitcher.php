<?php

namespace Drupal\entity_mesh\Language;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\language\ConfigurableLanguageManager;
use Drupal\language\LanguageNegotiatorInterface;

/**
 * Changes language negotiator on runtime.
 *
 * It is used in cases it is needed to render content
 * in a language which is not the current language.
 */
class LanguageNegotiatorSwitcher {

  /**
   * Used to change the language negotiator.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * Used to ensure we can change language / language negotiator.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Used to change default langcode of translated strings.
   *
   * @var \Drupal\Core\StringTranslation\TranslationManager
   */
  protected TranslationManager $translationManager;

  /**
   * Force the language to be a specific one.
   *
   * @var StaticLanguageNegotiator
   */
  protected StaticLanguageNegotiator $languageNegotiator;

  /**
   * Old translation language used code by string translation service.
   *
   * @var string|null
   */
  protected ?string $oldTranslationLangcode;

  /**
   * Old negotiator before the language negotiator added by us.
   *
   * @var \Drupal\language\LanguageNegotiatorInterface|null
   */
  protected ?LanguageNegotiatorInterface $oldNegotiator;

  public function __construct(LanguageManagerInterface $language_manager, ModuleHandlerInterface $module_handler, TranslationManager $translation_manager, StaticLanguageNegotiator $language_negotiator) {
    $this->languageManager = $language_manager;
    $this->moduleHandler = $module_handler;
    $this->translationManager = $translation_manager;
    $this->languageNegotiator = $language_negotiator;
  }

  /**
   * Switch to the selected language.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language we want to switch.
   */
  public function switchLanguage(LanguageInterface $language) {
    if ($this->moduleHandler->moduleExists('language') && $this->languageManager instanceof ConfigurableLanguageManager) {
      $this->oldNegotiator = $this->languageManager->getNegotiator();
      $this->oldTranslationLangcode = $this->languageManager->getCurrentLanguage()->getId();
      $this->languageNegotiator->setLanguage($language);
      $this->languageManager->setNegotiator($this->languageNegotiator);
      $this->translationManager->setDefaultLangcode($language->getId());
      $this->languageManager->reset();
    }
  }

  /**
   * Switch to the previous language.
   */
  public function switchBack() {
    if ($this->moduleHandler->moduleExists('language') && $this->languageManager instanceof ConfigurableLanguageManager
      && $this->oldNegotiator instanceof LanguageNegotiatorInterface
      && !empty($this->oldTranslationLangcode)) {
      $this->languageManager->setNegotiator($this->oldNegotiator);
      $this->translationManager->setDefaultLangcode($this->oldTranslationLangcode);
      $this->languageManager->reset();
      $this->oldNegotiator = NULL;
      $this->oldTranslationLangcode = NULL;
    }
  }

}
