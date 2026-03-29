<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_edges\Filters;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Filter the query to a specific language.
 */
class EntityFilterLanguage extends EdgeFilterBase {

  /**
   * Drupal language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * Drupal language manager.
   *
   * @return \Drupal\Core\Language\LanguageManagerInterface
   *   The Drupal language manager.
   */
  protected function languageManager(): LanguageManagerInterface {
    return $this->languageManager ??= \Drupal::languageManager();
  }

  /**
   * {@inheritdoc}
   */
  public function apply(QueryInterface $query): void {

    // Site is not multilingual, do not filter.
    if (!$this->languageManager()->isMultilingual()) {
      return;
    }

    // Entity has a langcode property.
    $field = $this->getQueryHelper()->getEntityType()->getKey('langcode');
    if (!$field) {
      return;
    }

    // Check if we are explicitly filtering by langcode.
    $langcode = $this->getFilter('langcode');
    if (!$langcode) {
      return;
    }

    // Ensure the language exists.
    $language = $this->languageManager()->getLanguage($langcode);
    if (!$language) {
      return;
    }

    // Filter by the language or undefined.
    $condition = $query->orConditionGroup()
      ->condition($field, $language->getId())
      ->condition($field, LanguageInterface::LANGCODE_NOT_APPLICABLE)
      ->condition($field, LanguageInterface::LANGCODE_NOT_SPECIFIED);

    $query->condition($condition);
  }

}
