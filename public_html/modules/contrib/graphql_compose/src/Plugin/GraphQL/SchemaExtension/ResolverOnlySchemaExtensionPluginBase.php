<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQL\SchemaExtension;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\graphql\Plugin\SchemaExtensionPluginInterface;
use Drupal\graphql_compose\Plugin\GraphQLComposeEntityTypeManager;
use Drupal\graphql_compose\Plugin\GraphQLComposeFieldTypeManager;
use Drupal\graphql_compose\Plugin\GraphQLComposeSchemaTypeManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A GraphQL Compose Schema Extension Plugin Base.
 *
 * This class is specifically for GraphQL Compose and not required if creating
 * standard Drupal GraphQL schemas.
 *
 * GraphQL Compose does not load .graphqls files.
 * To use normal GraphQL via .graphqls, do not use this class.
 *
 * Use
 * \Drupal\graphql\Plugin\GraphQL\SchemaExtension\SdlSchemaExtensionPluginBase
 * instead.
 *
 * @see https://drupal-graphql.gitbook.io/
 * @see https://www.drupal.org/project/graphql_compose/issues/3416642
 */
abstract class ResolverOnlySchemaExtensionPluginBase extends PluginBase implements SchemaExtensionPluginInterface, ContainerFactoryPluginInterface {

  /**
   * Create an instance of ResolverOnlySchemaExtensionPluginBase.
   *
   * @param array $configuration
   *   The plugin configuration array.
   * @param string $pluginId
   *   The plugin id.
   * @param array $pluginDefinition
   *   The plugin definition array.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\graphql_compose\Plugin\GraphQLComposeEntityTypeManager $gqlEntityTypeManager
   *   The entity type plugin manager service.
   * @param \Drupal\graphql_compose\Plugin\GraphQLComposeFieldTypeManager $gqlFieldTypeManager
   *   The field type plugin manager service.
   * @param \Drupal\graphql_compose\Plugin\GraphQLComposeSchemaTypeManager $gqlSchemaTypeManager
   *   The schema type plugin manager service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    array $pluginDefinition,
    protected ConfigFactoryInterface $configFactory,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected GraphQLComposeEntityTypeManager $gqlEntityTypeManager,
    protected GraphQLComposeFieldTypeManager $gqlFieldTypeManager,
    protected GraphQLComposeSchemaTypeManager $gqlSchemaTypeManager,
    protected LanguageManagerInterface $languageManager,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('graphql_compose.entity_type_manager'),
      $container->get('graphql_compose.field_type_manager'),
      $container->get('graphql_compose.schema_type_manager'),
      $container->get('language_manager'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @internal
   */
  public function getBaseDefinition() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   *
   * @internal
   */
  public function getExtensionDefinition() {
    return NULL;
  }

}
