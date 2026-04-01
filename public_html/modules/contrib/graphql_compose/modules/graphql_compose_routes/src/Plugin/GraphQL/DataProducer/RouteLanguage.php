<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_routes\Plugin\GraphQL\DataProducer;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extract the langcode from a path if not provided already.
 *
 * @DataProducer(
 *   id = "route_language",
 *   name = @Translation("Langcode from path"),
 *   description = @Translation("Find the langcode of a path."),
 *   produces = @ContextDefinition("string",
 *     label = @Translation("A langcode"),
 *   ),
 *   consumes = {
 *     "path" = @ContextDefinition("string",
 *       label = @Translation("String to try and extract langcode from"),
 *     ),
 *     "language" = @ContextDefinition("string",
 *       label = @Translation("Always use the langcode provided if available"),
 *       required = FALSE,
 *     ),
 *   },
 * )
 */
class RouteLanguage extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

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
   * Resolve the desired langcode from a path.
   *
   * @param string $path
   *   The path to extract the langcode from.
   * @param string|null $langcode
   *   The language code requested.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   Cache context.
   *
   * @return string|null
   *   The langcode to use.
   */
  public function resolve(string $path, ?string $langcode, FieldContext $context): ?string {

    // Site is not multilingual, return the langcode if provided.
    if (!$this->languageManager->isMultilingual()) {
      return $langcode;
    }

    // Get list of available lang codes for a pre match on the path.
    if (!$langcode) {
      $prefixes = array_keys($this->languageManager->getLanguages());

      // If the path starts with a langcode, use that.
      if (preg_match('#^/(' . implode('|', $prefixes) . ')($|/)#i', $path, $matches)) {

        // Ensure the language exists.
        $language = $this->languageManager->getLanguage($matches[1]);
        if ($language) {
          $langcode = $language->getId();
        }
      }
    }

    return $langcode;
  }

}
