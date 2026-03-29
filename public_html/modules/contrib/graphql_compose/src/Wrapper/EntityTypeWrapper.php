<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Wrapper;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\graphql_compose\LanguageInflector;
use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeEntityTypeInterface;
use Drupal\graphql_compose\Plugin\GraphQLComposeFieldTypeManager;
use Drupal\language\Entity\ContentLanguageSettings;
use function Symfony\Component\String\u;

/**
 * Wrapper for an entity.
 */
class EntityTypeWrapper {

  use StringTranslationTrait;

  /**
   * The wrapped entity (bundle).
   */
  protected ConfigEntityInterface|EntityTypeInterface $entity;

  /**
   * The wrapped entity type plugin.
   */
  protected GraphQLComposeEntityTypeInterface $entityTypePlugin;

  /**
   * Constructs a EntityTypeWrapper object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\graphql_compose\Plugin\GraphQLComposeFieldTypeManager $gqlFieldTypeManager
   *   The GraphQL Compose field type manager service.
   * @param \Drupal\graphql_compose\LanguageInflector $inflector
   *   The language inflector service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager service.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected GraphQLComposeFieldTypeManager $gqlFieldTypeManager,
    protected LanguageInflector $inflector,
    protected LanguageManagerInterface $languageManager,
  ) {}

  /**
   * Set the wrapped entity.
   *
   * @param mixed $entity
   *   The entity to wrap.
   *
   * @return self
   *   The current instance.
   */
  public function setEntity(mixed $entity): self {
    $this->entity = $entity;
    return $this;
  }

  /**
   * Get the wrapped entity (bundle).
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface|\Drupal\Core\Entity\EntityTypeInterface
   *   The wrapped entity (bundle).
   */
  public function getEntity(): ConfigEntityInterface|EntityTypeInterface {
    return $this->entity;
  }

  /**
   * Set the wrapped entity type plugin.
   *
   * @param \Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeEntityTypeInterface $entityTypePlugin
   *   The wrapped entity type plugin.
   *
   * @return self
   *   The current instance.
   */
  public function setEntityTypePlugin(GraphQLComposeEntityTypeInterface $entityTypePlugin): self {
    $this->entityTypePlugin = $entityTypePlugin;
    return $this;
  }

  /**
   * Get the wrapped entity type plugin.
   *
   * @return \Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeEntityTypeInterface
   *   The wrapped entity type plugin.
   */
  public function getEntityTypePlugin(): GraphQLComposeEntityTypeInterface {
    return $this->entityTypePlugin;
  }

  /**
   * Get the wrapped entity type.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   The wrapped entity type.
   */
  public function getEntityType(): EntityTypeInterface {
    return $this->entityTypePlugin->getEntityType();
  }

  /**
   * Type of this entity and bundle.
   *
   * @return string
   *   The GraphQL type name of the entity type. Eg paragraphText.
   *
   * @see hook_graphql_compose_singularize_alter()
   */
  public function getNameSdl(): string {
    $singular = $this->inflector->singularize($this->entity->id());

    return u($singular)
      ->ascii()
      ->title()
      ->prepend($this->entityTypePlugin->getPrefix())
      ->camel()
      ->toString();
  }

  /**
   * Type of this entity and bundle, plural. Eg paragraphTexts. newsItems.
   *
   * @return string
   *   The GraphQL type name of the entity type, plural.
   *
   * @see hook_graphql_compose_pluralize_alter()
   */
  public function getNamePluralSdl(): string {
    $singular = $this->inflector->singularize($this->entity->id());
    $plural = $this->inflector->pluralize($singular);

    return u($plural)
      ->ascii()
      ->title()
      ->prepend($this->entityTypePlugin->getPrefix())
      ->camel()
      ->toString();
  }

  /**
   * Type for the Schema. Title cased singular.
   *
   * @return string
   *   The GraphQL type of the entity type. Eg ParagraphText.
   */
  public function getTypeSdl(): string {
    return u($this->getNameSdl())
      ->camel()
      ->title()
      ->toString();
  }

  /**
   * Return the bundle description or the defined description on the plugin.
   *
   * @return string|null
   *   The description of the wrapped entity.
   *
   * @disregard P1009 Undefined type
   */
  public function getDescription(): ?string {
    return method_exists($this->entity, 'getDescription')
      ? $this->entity->getDescription()
      : NULL;
  }

  /**
   * Check if this entity bundle is enabled.
   *
   * @return bool
   *   True if the bundle is enabled.
   */
  public function isEnabled(): bool {
    if ($this->entity instanceof ConfigEntityTypeInterface) {
      return TRUE;
    }

    return (bool) $this->getSetting('enabled') ?: FALSE;
  }

  /**
   * Enabled single resolution query for type. Eg nodePage()
   *
   * @return bool
   *   True if the query load option is enabled.
   */
  public function isQueryLoadEnabled(): bool {
    return (bool) $this->getSetting('query_load_enabled') ?: FALSE;
  }

  /**
   * Check if the bundle has multiple and enabled translations.
   *
   * @return bool
   *   True if we would want to use this as a translation.
   */
  public function isTranslatableContent(): bool {
    // If the site is not multilingual, we don't need to check further.
    if (!$this->languageManager->isMultilingual()) {
      return FALSE;
    }

    // If the entity type does not have a path field, it's no concern.
    if (!array_key_exists('path', $this->entityTypePlugin->getBaseFields())) {
      return FALSE;
    }

    // Check if the entity bundle is translatable.
    $config = ContentLanguageSettings::loadByEntityTypeBundle(
      $this->getEntityType()->id(),
      $this->entity->id()
    );

    return $config->isLanguageAlterable();
  }

  /**
   * Get a config setting.
   *
   * @param string $setting
   *   The setting to get.
   *
   * @return mixed
   *   The setting value or null.
   */
  public function getSetting(string $setting): mixed {
    $settings = $this->configFactory->get('graphql_compose.settings');

    $parts = [
      'entity_config',
      $this->getEntityType()->id(),
      $this->entity->id(),
      $setting,
    ];

    return $settings->get(implode('.', $parts)) ?: NULL;
  }

}
