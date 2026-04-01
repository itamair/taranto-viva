<?php

declare(strict_types=1);

namespace Drupal\graphql_compose;

use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use Doctrine\Inflector\Language;
use Doctrine\Inflector\LanguageInflectorFactory;
use Doctrine\Inflector\Rules\Patterns;
use Doctrine\Inflector\Rules\Ruleset;
use Doctrine\Inflector\Rules\Substitution;
use Doctrine\Inflector\Rules\Substitutions;
use Doctrine\Inflector\Rules\Transformations;
use Doctrine\Inflector\Rules\Word;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Language inflector service.
 */
class LanguageInflector {

  use StringTranslationTrait;

  /**
   * The inflector service.
   *
   * @var \Doctrine\Inflector\Inflector
   */
  protected Inflector $inflector;

  /**
   * Construct language inflector.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory service.
   */
  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected ConfigFactoryInterface $configFactory,
  ) {
    $this->inflector = $this->getInflectorFactory()->build();
  }

  /**
   * Get the config object.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The config object.
   */
  protected function getConfig(): ImmutableConfig {
    return $this->configFactory->get('graphql_compose.settings');
  }

  /**
   * Get the inflector to build.
   *
   * This is a good spot to override the service with a custom inflector.
   * OR to add any custom inflector rules.
   *
   * @return \Doctrine\Inflector\LanguageInflectorFactory
   *   The inflector for language.
   *
   * @see https://www.doctrine-project.org/projects/doctrine-inflector/en/2.0/index.html
   */
  protected function getInflectorFactory(): LanguageInflectorFactory {
    $factory = InflectorFactory::createForLanguage($this->getInflectorLanguage());

    $factory->withSingularRules(
      new Ruleset(
        new Transformations(),
        new Patterns(),
        new Substitutions(
          // Drupal'isms point to 'media' being used in a singular form.
          // Eg getMediaType() not getMediumType().
          // Adding an underscore to the singular form bypasses the inflector.
          new Substitution(new Word('media'), new Word('_media'))
        )
      ),
    );

    return $factory;
  }

  /**
   * Get the language to use for inflection.
   *
   * This is a good spot to override the service to a specific language.
   *
   * @return string
   *   The language name that works with doctrine inflector.
   */
  protected function getInflectorLanguage(): string {

    $langcode = $this->getConfig()->get('settings.inflector_langcode');

    switch ($langcode) {
      case 'fr':
        return Language::FRENCH;

      case 'nb':
        return Language::NORWEGIAN_BOKMAL;

      case 'pt-pt':
      case 'pt-br':
        return Language::PORTUGUESE;

      case 'es':
        return Language::SPANISH;

      case 'tr':
        return Language::TURKISH;

      default:
        return Language::ENGLISH;
    }
  }

  /**
   * Returns the singular form of a string.
   *
   * @param string $original
   *   The string to be singularized.
   *
   * @return string
   *   Singular form of a string.
   *
   * @see hook_graphql_compose_singularize_alter()
   */
  public function singularize(string $original): string {

    // Allow user to disable this functionality.
    $inflector_singularize = $this->getConfig()->get('settings.inflector_singularize') ?: FALSE;
    if (!$inflector_singularize) {
      return $original;
    }

    // Remove any leading slash added by inflector rule bypasses.
    $singular = $this->inflector->singularize($original);
    $singular = ltrim($singular, '_');

    $this->moduleHandler->invokeAll('graphql_compose_singularize_alter', [
      $original,
      &$singular,
    ]);

    return $singular;
  }

  /**
   * Returns the plural forms of a string.
   *
   * @param string $singular
   *   Singular form of a string.
   *
   * @return string
   *   Plural form of a string.
   *
   * @see hook_graphql_compose_pluralize_alter()
   */
  public function pluralize(string $singular): string {
    $plural = $this->inflector->pluralize($singular);

    $this->moduleHandler->invokeAll('graphql_compose_pluralize_alter', [
      $singular,
      &$plural,
    ]);

    // Failsafe pluralize if singular and plural are the same. Eg:
    // - The singular of news is news.
    // - The plural of news is news.
    // So we add _items to the plural to make it news_items.
    // Which works better than newss.
    if ($plural === $singular) {
      $plural .= '_items';
    }

    return $plural;
  }

}
