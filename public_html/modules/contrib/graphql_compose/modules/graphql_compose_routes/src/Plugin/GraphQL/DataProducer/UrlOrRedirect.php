<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_routes\Plugin\GraphQL\DataProducer;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Load a Route or Redirect based on Path.
 *
 * @DataProducer(
 *   id = "url_or_redirect",
 *   name = @Translation("Load Url or Redirect"),
 *   description = @Translation("Loads a Url or Redirect by the path."),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("Route or Redirect"),
 *   ),
 *   consumes = {
 *     "path" = @ContextDefinition("string",
 *       label = @Translation("Path"),
 *       required = FALSE,
 *     ),
 *   },
 * )
 */
class UrlOrRedirect extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a \Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Path\PathValidatorInterface $pathValidator
   *   Drupal path validator.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   Drupal language manager.
   * @param \Drupal\redirect\RedirectRepository|null $redirectRepository
   *   Redirect module repository.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected PathValidatorInterface $pathValidator,
    protected LanguageManagerInterface $languageManager,
    protected $redirectRepository = NULL,
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
      $container->get('path.validator'),
      $container->get('language_manager'),
      $container->get('redirect.repository', ContainerInterface::NULL_ON_INVALID_REFERENCE),
    );
  }

  /**
   * Resolve a URL or Redirect off path.
   *
   * @param string|null $path
   *   Path to resolve.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   Metadata to attach cacheability to.
   *
   * @return null|\Drupal\redirect\Entity\Redirect|Url
   *   Path resolution result.
   */
  public function resolve(?string $path, FieldContext $context): mixed {

    // Exit early with redirect.
    $redirect = $this->getRedirect($path, $context);
    if ($redirect) {
      return $this->isAccessible($redirect->getRedirectUrl(), $context) ? $redirect : NULL;
    }

    // Convert path string to a Url object.
    $url = $this->pathValidator->getUrlIfValidWithoutAccessCheck($path);
    if (!$url) {
      $context->addCacheTags(['4xx-response']);
      return NULL;
    }

    return $this->isAccessible($url, $context) ? $url : NULL;
  }

  /**
   * Get the URL for a redirect.
   *
   * @param string $path
   *   Path to check.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   Metadata to attach cacheability to.
   *
   * @return null|\Drupal\redirect\Entity\Redirect
   *   Redirect entity if found.
   */
  protected function getRedirect(string $path, FieldContext $context): mixed {

    // Redirect module requires the current language code to get results.
    $langcode = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_URL)->getId();

    $parsed_url = UrlHelper::parse(trim($path));

    return $this->redirectRepository
      ? $this->redirectRepository->findMatchingRedirect($parsed_url['path'], $parsed_url['query'], $langcode)
      : NULL;
  }

  /**
   * Check the URL goes somewhere.
   *
   * @param \Drupal\Core\Url $url
   *   Url to check.
   *
   * @return bool
   *   Whether the url should be a route.
   */
  protected function hasLink(Url $url): bool {
    return $url->isRouted() ? $url->getRouteName() !== '<nolink>' : TRUE;
  }

  /**
   * Check if the URL is accessible.
   *
   * If not a routed URL, then it is assumed to be accessible.
   *
   * @param \Drupal\Core\Url $url
   *   Url to check.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   Metadata to attach cacheability to.
   *
   * @return bool
   *   Whether the url should be accessible.
   */
  protected function isAccessible(Url $url, FieldContext $context): bool {
    $access = $url->access(NULL, TRUE);
    $context->addCacheableDependency($access);

    if (!$this->hasLink($url) || !$access->isAllowed()) {
      $context->addCacheTags(['4xx-response']);
      return FALSE;
    }

    return TRUE;
  }

}
