<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQLCompose;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\Entity\BaseFieldOverride;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\graphql_compose\Plugin\GraphQLComposeEntityTypeManager;
use Drupal\graphql_compose\Plugin\GraphQLComposeFieldTypeManager;
use Drupal\graphql_compose\Plugin\GraphQLComposeSchemaTypeManager;
use Drupal\graphql_compose\Wrapper\EntityTypeWrapper;
use Symfony\Component\DependencyInjection\ContainerInterface;

use function Symfony\Component\String\u;

/**
 * Base class that can be used for schema extension plugins.
 */
abstract class GraphQLComposeFieldTypeBase extends PluginBase implements GraphQLComposeFieldTypeInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs a GraphQLComposeFieldTypeBase object.
   *
   * @param array $configuration
   *   The plugin configuration array.
   * @param string $plugin_id
   *   The plugin id.
   * @param array $plugin_definition
   *   The plugin definition array.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Drupal config factory.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Drupal entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   Drupal entity type bundle info.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Drupal entity type manager.
   * @param \Drupal\graphql_compose\Plugin\GraphQLComposeEntityTypeManager $gqlEntityTypeManager
   *   GraphQL Compose entity type plugin manager.
   * @param \Drupal\graphql_compose\Plugin\GraphQLComposeFieldTypeManager $gqlFieldTypeManager
   *   GraphQL Compose field type plugin manager.
   * @param \Drupal\graphql_compose\Plugin\GraphQLComposeSchemaTypeManager $gqlSchemaTypeManager
   *   GraphQL Compose schema type plugin manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   Drupal language manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Drupal module handler.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    array $plugin_definition,
    protected ConfigFactoryInterface $configFactory,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected GraphQLComposeEntityTypeManager $gqlEntityTypeManager,
    protected GraphQLComposeFieldTypeManager $gqlFieldTypeManager,
    protected GraphQLComposeSchemaTypeManager $gqlSchemaTypeManager,
    protected LanguageManagerInterface $languageManager,
    protected ModuleHandlerInterface $moduleHandler,
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
      $container->get('config.factory'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
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
   */
  public function getFieldDefinition(): ?FieldDefinitionInterface {
    return $this->configuration['field_definition'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldType(): string {
    return $this->configuration['field_type'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldName(): string {
    return $this->configuration['field_name'];
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityWrapper(EntityTypeWrapper $entity_wrapper): void {
    $this->configuration['entity'] = $entity_wrapper;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityWrapper(): ?EntityTypeWrapper {
    return $this->configuration['entity'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): ?string {
    $description = $this->configuration['description'] ?? NULL;
    return is_null($description) ? NULL : (string) $description;
  }

  /**
   * {@inheritdoc}
   */
  public function getNameSdl(): string {
    $name_sdl = $this->configuration['name_sdl'];

    return u($name_sdl)
      ->trimPrefix('field_')
      ->camel()
      ->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function getTypeSdl(): string {
    return $this->configuration['type_sdl'] ?? $this->pluginDefinition['type_sdl'];
  }

  /**
   * {@inheritdoc}
   */
  public function isMultiple(): bool {
    return $this->configuration['multiple'] ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isRequired(): bool {
    return $this->configuration['required'] ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isBaseField(): bool {
    $field_definition = $this->getFieldDefinition();

    return ($field_definition instanceof BaseFieldDefinition || $field_definition instanceof BaseFieldOverride);
  }

  /**
   * {@inheritdoc}
   */
  public function getArgsSdl(): array {
    return [];
  }

}
