<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQL\SchemaExtension;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistryInterface;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The core schema without any entity types. Just config and utility.
 *
 * @SchemaExtension(
 *   id = "graphql_compose_information",
 *   name = "GraphQL Compose Information",
 *   description = @Translation("Misc schema extensions for GraphQL Compose."),
 *   schema = "graphql_compose",
 * )
 *
 * @internal
 */
class InformationSchemaExtension extends ResolverOnlySchemaExtensionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected AliasManagerInterface $pathAliasManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition
    );

    $instance->pathAliasManager = $container->get('path_alias.manager');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function registerResolvers(ResolverRegistryInterface $registry): void {
    $builder = new ResolverBuilder();

    $settings = $this->configFactory->get('graphql_compose.settings');

    // Utility extensions.
    $registry->addFieldResolver(
      'Mutation',
      '_',
      $builder->callback(fn () => TRUE),
    );

    $registry->addFieldResolver(
      'Subscription',
      '_',
      $builder->callback(fn () => TRUE),
    );

    // Schema information.
    $registry->addFieldResolver(
      'Query',
      'info',
      $builder->callback(fn () => TRUE)
    );

    // Add schema information.
    $registry->addFieldResolver(
      'SchemaInformation',
      'description',
      $builder->callback(fn () => $settings->get('settings.schema_description') ?: NULL)
    );

    $registry->addFieldResolver(
      'SchemaInformation',
      'version',
      $builder->callback(fn () => $settings->get('settings.schema_version') ?: NULL)
    );

    // Add site settings.
    if ($settings->get('settings.site_front')) {
      $registry->addFieldResolver(
        'SchemaInformation',
        'home',
        $builder->callback(function () {
          $path = $this->configFactory->get('system.site')->get('page.front') ?: NULL;

          return $path ? $this->pathAliasManager->getAliasByPath($path) : NULL;
        })
      );
    }

    if ($settings->get('settings.site_slogan')) {
      $registry->addFieldResolver(
        'SchemaInformation',
        'slogan',
        $builder->callback(fn () => $this->configFactory->get('system.site')->get('slogan') ?: NULL)
      );
    }

    if ($settings->get('settings.site_name')) {
      $registry->addFieldResolver(
        'SchemaInformation',
        'name',
        $builder->callback(fn () => $this->configFactory->get('system.site')->get('name') ?: NULL)
      );
    }

    // Add user defined settings.
    $custom_values = [];
    $custom_settings = $settings->get('settings.custom') ?: [];

    foreach ($custom_settings as $setting) {
      $value = $setting['value'];
      $name = $setting['name'];
      $type = $setting['type'];
      // Coerce user values back into booleans.
      if ($type === 'boolean') {
        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
      }
      // Combine multiples.
      $custom_values[$name][] = $value;
    }

    // Flatten single values.
    $custom_values = array_map(
      fn ($value) => count($value) === 1 ? reset($value) : $value,
      $custom_values
    );

    foreach ($custom_values as $field_name => $custom_value) {
      $registry->addFieldResolver(
        'SchemaInformation',
        $field_name,
        $builder->produce('plain_token')
          ->map('value', $builder->fromValue($custom_value))
      );
    }

  }

}
