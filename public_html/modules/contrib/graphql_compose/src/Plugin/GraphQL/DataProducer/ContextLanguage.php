<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQL\DataProducer;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Set the language in the context for translation.
 *
 * @DataProducer(
 *   id = "language_context",
 *   name = @Translation("Language context"),
 *   description = @Translation("Set the language in the context for translation."),
 *   produces = @ContextDefinition("string",
 *     label = @Translation("Language"),
 *   ),
 *   consumes = {
 *     "language" = @ContextDefinition("string",
 *       label = @Translation("Language"),
 *     ),
 *   },
 * )
 */
class ContextLanguage extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a \Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   Drupal language manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected LanguageManagerInterface $languageManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('language_manager'),
    );
  }

  /**
   * Set the language.
   *
   * Note: This is quirky. The "last requested language" will be used for
   * translation. This is not necessarily the language of the current request.
   *
   * It can do weird things if changing language mid-query.
   *
   * @param string|null $langcode
   *   The language to resolve the url in.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   The cache context.
   *
   * @return string|null
   *   The language in use.
   */
  public function resolve(?string $langcode, FieldContext $context): ?string {

    if ($langcode && $this->languageManager->isMultilingual()) {

      $language = $this->languageManager->getLanguage($langcode);
      if ($language) {
        $context->setContextLanguage($langcode);
        $this->languageManager->setConfigOverrideLanguage($language);
      }

      $this->languageManager->reset();
    }

    return $langcode;
  }

}
