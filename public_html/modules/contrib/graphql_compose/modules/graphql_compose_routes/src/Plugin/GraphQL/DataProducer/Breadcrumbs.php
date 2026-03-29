<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_routes\Plugin\GraphQL\DataProducer;

use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\graphql_compose_routes\GraphQL\Buffers\SubrequestBuffer;
use GraphQL\Deferred;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Load breadcrumbs for a URL.
 *
 * @DataProducer(
 *   id = "breadcrumbs",
 *   name = @Translation("Breadcrumbs for a route"),
 *   description = @Translation("Based on a route URL."),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("Breadcrumbs"),
 *   ),
 *   consumes = {
 *     "url" = @ContextDefinition("any",
 *       label = @Translation("Requested URL to build a breadcrumb off"),
 *       required = FALSE,
 *     ),
 *   },
 * )
 */
class Breadcrumbs extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

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
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   Drupal current route match.
   * @param \Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface $breadcrumbManager
   *   Drupal breadcrumb manager.
   * @param \Drupal\graphql_compose_routes\GraphQL\Buffers\SubrequestBuffer $subrequestBuffer
   *   GraphQL sub request buffer.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   Drupal renderer.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected LanguageManagerInterface $languageManager,
    protected RouteMatchInterface $routeMatch,
    protected BreadcrumbBuilderInterface $breadcrumbManager,
    protected SubrequestBuffer $subrequestBuffer,
    protected RendererInterface $renderer,
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
      $container->get('current_route_match'),
      $container->get('breadcrumb'),
      $container->get('graphql_compose_routes.buffer.subrequest'),
      $container->get('renderer'),
    );
  }

  /**
   * Resolve breadcrumbs via subrequest.
   *
   * @param \Drupal\Core\Url|null $url
   *   Url to resolve breadcrumbs for.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   Cache context.
   *
   * @return array|Deferred
   *   Array of breadcrumb links.
   */
  public function resolve(?Url $url, FieldContext $context): array|Deferred {
    if (!$url instanceof Url) {
      return [];
    }

    $resolver = $this->subrequestBuffer->add(
      $url,
      function () {
        $render_context = new RenderContext();
        return $this->renderer->executeInRenderContext($render_context, function () {
          $this->languageManager->reset();
          return $this->breadcrumbManager->build($this->routeMatch)?->getLinks() ?: [];
        });
      }
    );

    return new Deferred(function () use ($resolver, $context) {
      $links = [];

      /** @var \Drupal\Core\Link[] $breadcrumbs */
      $breadcrumbs = $resolver();

      foreach ($breadcrumbs as $link) {
        /** @var \Drupal\Core\GeneratedUrl $generated */
        $generated = $link->getUrl()->toString(TRUE);
        $context->addCacheableDependency($generated);

        // Add cache tags for other routed links.
        if ($link->getUrl()->isRouted()) {
          $parameters = array_filter($link->getUrl()->getRouteParameters(), is_numeric(...));
          foreach ($parameters as $type => $id) {
            $context->addCacheTags([$type . ':' . $id]);
          }
        }

        $links[] = [
          'title' => $link->getText(),
          'url' => $generated->getGeneratedUrl(),
          'internal' => $link->getUrl()->isRouted(),
        ];
      }

      return $links;
    });
  }

}
