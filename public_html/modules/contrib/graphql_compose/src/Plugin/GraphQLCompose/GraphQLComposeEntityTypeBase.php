<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQLCompose;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistryInterface;
use Drupal\graphql_compose\Plugin\GraphQLComposeFieldTypeManager;
use Drupal\graphql_compose\Plugin\GraphQLComposeSchemaTypeManager;
use Drupal\graphql_compose\Wrapper\EntityTypeWrapper;
use GraphQL\Error\UserError;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use Symfony\Component\DependencyInjection\ContainerInterface;
use function Symfony\Component\String\u;

/**
 * Base class that can be used for schema extension plugins.
 */
abstract class GraphQLComposeEntityTypeBase extends PluginBase implements GraphQLComposeEntityTypeInterface, ContainerFactoryPluginInterface {

  /**
   * Static storage of bundles for plugin.
   *
   * @var \Drupal\graphql_compose\Wrapper\EntityTypeWrapper[]
   */
  private array $bundles;

  /**
   * Constructs a GraphQLComposeEntityTypeBase object.
   *
   * @param array $configuration
   *   The plugin configuration array.
   * @param string $plugin_id
   *   The plugin id.
   * @param array $plugin_definition
   *   The plugin definition array.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Drupal config factory service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   Drupal entity type bundle service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Drupal entity type manager service.
   * @param \Drupal\graphql_compose\Plugin\GraphQLComposeFieldTypeManager $gqlFieldTypeManager
   *   GraphQL Compose field type plugin manager.
   * @param \Drupal\graphql_compose\Plugin\GraphQLComposeSchemaTypeManager $gqlSchemaTypeManager
   *   GraphQL Compose schema type plugin manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   Drupal language manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Drupal module handler service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    array $plugin_definition,
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    protected EntityTypeManagerInterface $entityTypeManager,
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
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('graphql_compose.field_type_manager'),
      $container->get('graphql_compose.schema_type_manager'),
      $container->get('language_manager'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId(): string {
    return $this->getDerivativeId() ?: $this->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType(): EntityTypeInterface {
    return $this->entityTypeManager->getDefinition($this->getEntityTypeId());
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return (string) $this->t('Entity type @id.', [
      '@id' => $this->getEntityTypeId(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getInterfaces(?string $bundle_id = NULL): array {
    $interfaces = $this->pluginDefinition['interfaces'] ?? [];

    if ($this->gqlFieldTypeManager->getInterfaceFields($this->getEntityTypeId())) {
      $interfaces[] = $this->getInterfaceTypeSdl();
    }

    $this->moduleHandler->invokeAll('graphql_compose_entity_interfaces_alter', [
      &$interfaces,
      $this,
      $bundle_id,
    ]);

    return array_unique($interfaces);
  }

  /**
   * {@inheritdoc}
   */
  public function getPrefix(): string {
    return $this->pluginDefinition['prefix'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getNameSdl(): string {
    return u($this->getTypeSdl())
      ->camel()
      ->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function getTypeSdl(): string {
    $type = $this->pluginDefinition['type_sdl'] ?? $this->getEntityTypeId();

    return u($type)
      ->camel()
      ->title()
      ->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFields(): array {
    $base_fields = $this->pluginDefinition['base_fields'] ?? [];

    $this->moduleHandler->invokeAll('graphql_compose_entity_base_fields_alter', [
      &$base_fields,
      $this->getEntityTypeId(),
    ]);

    return $base_fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getUnionTypeSdl(): string {
    return u($this->getTypeSdl())
      ->append('Union')
      ->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function getInterfaceTypeSdl(): string {
    return u($this->getTypeSdl())
      ->append('Interface')
      ->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function getBundle(string $bundle_id): ?EntityTypeWrapper {
    $bundles = $this->getBundles();
    return $bundles[$bundle_id] ?? NULL;
  }

  /**
   * Wrap a bundle into a utility wrapper.
   *
   * @param mixed $bundle
   *   The bundle to wrap.
   *
   * @return \Drupal\graphql_compose\Wrapper\EntityTypeWrapper
   *   The wrapped bundle.
   */
  protected function wrapBundle($bundle): EntityTypeWrapper {
    return \Drupal::service('graphql_compose.entity_type_wrapper')
      ->setEntityTypePlugin($this)
      ->setEntity($bundle);
  }

  /**
   * {@inheritdoc}
   */
  public function getBundles(): array {
    if (isset($this->bundles)) {
      return $this->bundles;
    }

    $this->bundles = [];

    $entity_type = $this->getEntityType();
    $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($this->getEntityTypeId());

    if ($storage_type = $entity_type->getBundleEntityType()) {
      $entity_types = $this->entityTypeManager->getStorage($storage_type)->loadMultiple();
    }

    foreach (array_keys($bundle_info) as $bundle_id) {
      $bundle = $this->wrapBundle($entity_types[$bundle_id] ?? $entity_type);
      if ($bundle->isEnabled()) {
        $this->bundles[$bundle_id] = $bundle;
      }
    }

    return $this->bundles ?: [];
  }

  /**
   * {@inheritdoc}
   *
   * Register unions and interfaces only if there is multiple enabled bundles.
   */
  public function registerTypes(): void {
    $bundles = $this->getBundles();
    if (!$bundles) {
      return;
    }

    $this->registerEntityInterface();
    $this->registerEntityUnion();
    $this->registerEntityQuery();

    foreach ($bundles as $bundle) {
      $this->registerBundleTypes($bundle);
      $this->registerBundleQueries($bundle);
      $this->registerBundleFieldUnions($bundle);
    }
  }

  /**
   * Register a generic entity wide interface.
   */
  protected function registerEntityInterface(): void {
    $interface_fields = $this->gqlFieldTypeManager->getInterfaceFields($this->getEntityTypeId());
    if ($interface_fields) {
      $interface = new InterfaceType([
        'name' => $this->getInterfaceTypeSdl(),
        'description' => $this->getDescription(),
        'fields' => function () use ($interface_fields) {
          $fields = [];
          foreach ($interface_fields as $field) {
            $fields[$field->getNameSdl()] = [
              'type' => $this->gqlSchemaTypeManager->get(
                $field->getTypeSdl(),
                $field->isMultiple(),
                $field->isRequired()
              ),
              'description' => $field->getDescription(),
            ];
          }

          return $fields;
        },
      ]);

      $this->gqlSchemaTypeManager->add($interface);
    }
  }

  /**
   * Register a generic entity wide union.
   */
  protected function registerEntityUnion(): void {
    $union_types = array_map(
      fn(EntityTypeWrapper $bundle): string => $bundle->getTypeSdl(),
      $this->getBundles()
    );

    $entity_union = new UnionType([
      'name' => $this->getUnionTypeSdl(),
      'description' => $this->getDescription(),
      'types' => fn() => array_map(
        $this->gqlSchemaTypeManager->get(...),
        $union_types ?: ['UnsupportedType']
      ),
    ]);

    $this->gqlSchemaTypeManager->add($entity_union);
  }

  /**
   * Register a generic entity wide query.
   */
  protected function registerEntityQuery(): void {
    $enabled_query_bundles = array_filter(
      $this->getBundles(),
      fn(EntityTypeWrapper $bundle) => $bundle->isQueryLoadEnabled()
    );

    if ($this->isQueryLoadSimple() && $enabled_query_bundles) {

      // Entities without bundles shouldn't return a union.
      $query_type = $this->getEntityType()->getBundleEntityType()
        ? $this->getUnionTypeSdl()
        : $this->getTypeSdl();

      $entityQuery = new ObjectType([
        'name' => 'Query',
        'fields' => fn() => [
          $this->getNameSdl() => [
            'type' => $this->gqlSchemaTypeManager->get($query_type),
            'description' => (string) $this->t('Load a @type entity by id.', [
              '@type' => $this->getTypeSdl(),
            ]),
            'args' => array_filter([
              'id' => [
                'type' => Type::nonNull(Type::id()),
                'description' => (string) $this->t('The id of the @type to load.', [
                  '@type' => $this->getTypeSdl(),
                ]),
              ],
              'langcode' => $this->languageManager->isMultilingual() ? [
                'type' => Type::string(),
                'description' => (string) $this->t('Optionally set the response language. Eg en, ja, fr.'),
              ] : [],
              'revision' => $this->getEntityType()->isRevisionable() ? [
                'type' => Type::id(),
                'description' => (string) $this->t('Optionally set the revision of the entity. Eg current, latest, or an ID.'),
              ] : [],
            ]),
          ],
        ],
      ]);

      $this->gqlSchemaTypeManager->extend($entityQuery);
    }
  }

  /**
   * Register a bundle types into the schema.
   *
   * @param \Drupal\graphql_compose\Wrapper\EntityTypeWrapper $bundle
   *   The bundle to register.
   */
  protected function registerBundleTypes(EntityTypeWrapper $bundle): void {
    $fields = $this->gqlFieldTypeManager->getBundleFields(
      $this->getEntityTypeId(),
      $bundle->getEntity()->id()
    );

    // Create bundle type.
    $entityType = new ObjectType([
      'name' => $bundle->getTypeSdl(),
      'description' => $bundle->getDescription() ?: $this->getDescription(),
      'interfaces' => fn() => array_map(
        $this->gqlSchemaTypeManager->get(...),
        $this->getInterfaces($bundle->getEntity()->id())
      ),
      'fields' => function () use ($fields) {
        $result = [];
        foreach ($fields as $field) {
          $result[$field->getNameSdl()] = [
            'description' => $field->getDescription(),
            'type' => $this->gqlSchemaTypeManager->get(
              $field->getTypeSdl(),
              $field->isMultiple(),
              $field->isRequired()
            ),
            'args' => $field->getArgsSdl(),
          ];
        }
        return $result;
      },
    ]);

    $this->gqlSchemaTypeManager->add($entityType);
  }

  /**
   * Register individual bundle queries into the schema.
   *
   * @param \Drupal\graphql_compose\Wrapper\EntityTypeWrapper $bundle
   *   The bundle to register.
   */
  protected function registerBundleQueries(EntityTypeWrapper $bundle): void {
    if (!$this->isQueryLoadSimple() && $bundle->isQueryLoadEnabled()) {
      $entityQuery = new ObjectType([
        'name' => 'Query',
        'fields' => fn() => [
          $bundle->getNameSdl() => [
            'type' => $this->gqlSchemaTypeManager->get($bundle->getTypeSdl()),
            'description' => (string) $this->t('Load a @bundle entity by id', [
              '@bundle' => $bundle->getTypeSdl(),
            ]),
            'args' => array_filter([
              'id' => [
                'type' => Type::nonNull(Type::id()),
                'description' => (string) $this->t('The id of the @bundle to load.', [
                  '@bundle' => $bundle->getTypeSdl(),
                ]),
              ],
              'langcode' => $this->languageManager->isMultilingual() ? [
                'type' => Type::string(),
                'description' => (string) $this->t('Optionally set the response language. Eg en, ja, fr.'),
              ] : [],
              'revision' => $this->getEntityType()->isRevisionable() ? [
                'type' => Type::id(),
                'description' => (string) $this->t('Optionally set the revision of the entity. Eg current, latest, or an ID.'),
              ] : [],
            ]),
          ],
        ],
      ]);

      $this->gqlSchemaTypeManager->extend($entityQuery);
    }
  }

  /**
   * Register a bundle field union types into the schema.
   *
   * @param \Drupal\graphql_compose\Wrapper\EntityTypeWrapper $bundle
   *   The bundle to register.
   */
  protected function registerBundleFieldUnions(EntityTypeWrapper $bundle): void {
    $fields = $this->gqlFieldTypeManager->getBundleFields(
      $this->getEntityTypeId(),
      $bundle->getEntity()->id()
    );

    // Add per-field union types.
    foreach ($fields as $field_plugin) {
      if (!$field_plugin instanceof FieldUnionInterface) {
        continue;
      }

      // The unsupported field points to an unsupported type.
      if ($field_plugin->getUnionTypeSdl() === 'UnsupportedType') {
        continue;
      }

      // Generic unions return a generic entity union.
      if ($field_plugin->isGenericUnion()) {
        continue;
      }

      // Single unions just return the type.
      if ($field_plugin->isSingleUnion()) {
        continue;
      }

      $union = new UnionType([
        'name' => $field_plugin->getUnionTypeSdl(),
        'description' => $field_plugin->getDescription(),
        'types' => fn() => array_map(
          $this->gqlSchemaTypeManager->get(...),
          $field_plugin->getUnionTypeMapping() ?: ['UnsupportedType']
        ),
      ]);

      $this->gqlSchemaTypeManager->add($union);
    }
  }

  /**
   * {@inheritdoc}
   *
   * Resolve unions only if there is multiple enabled bundles.
   */
  public function registerResolvers(ResolverRegistryInterface $registry, ResolverBuilder $builder): void {
    $bundles = $this->getBundles();
    if (!$bundles) {
      return;
    }

    $this->resolveEntityQuery($registry, $builder);
    $this->resolveEntityUnion($registry, $builder);

    foreach ($bundles as $bundle) {
      $this->resolveBundleTypes($registry, $builder, $bundle);
      $this->resolveBundleQueries($registry, $builder, $bundle);
      $this->resolveBundleFieldUnions($registry, $builder, $bundle);
    }
  }

  /**
   * Resolve generic entity query.
   *
   * @param \Drupal\graphql\GraphQL\ResolverRegistryInterface $registry
   *   The resolver registry.
   * @param \Drupal\graphql\GraphQL\ResolverBuilder $builder
   *   The resolver builder.
   */
  protected function resolveEntityQuery(ResolverRegistryInterface $registry, ResolverBuilder $builder): void {
    // Resolve generic load by id query.
    $enabled_query_bundles = array_filter(
      $this->getBundles(),
      fn(EntityTypeWrapper $bundle) => $bundle->isQueryLoadEnabled()
    );

    // Limit allowed bundle types.
    $enabled_query_bundle_ids = array_map(
      fn(EntityTypeWrapper $bundle) => $bundle->getEntity()->id(),
      $enabled_query_bundles
    );

    if ($this->isQueryLoadSimple() && $enabled_query_bundles) {
      $registry->addFieldResolver(
        'Query',
        $this->getNameSdl(),
        $builder->compose(
          $builder->produce('entity_load_by_uuid_or_id')
            ->map('type', $builder->fromValue($this->getEntityTypeId()))
            ->map('bundles', $builder->fromValue($enabled_query_bundle_ids))
            ->map('identifier', $builder->fromArgument('id'))
            ->map('language', $builder->fromArgument('langcode')),
          $builder->produce('entity_load_revision')
            ->map('entity', $builder->fromParent())
            ->map('identifier', $builder->fromArgument('revision'))
            ->map('language', $builder->fromArgument('langcode'))
        )
      );
    }
  }

  /**
   * Resolve generic entity wide union.
   *
   * @param \Drupal\graphql\GraphQL\ResolverRegistryInterface $registry
   *   The resolver registry.
   * @param \Drupal\graphql\GraphQL\ResolverBuilder $builder
   *   The resolver builder.
   */
  protected function resolveEntityUnion(ResolverRegistryInterface $registry, ResolverBuilder $builder): void {
    // The expected class for the entity type.
    $class = $this->entityTypeManager
      ->getDefinition($this->getEntityTypeId())
      ->getClass();

    // Resolve generic entity wide union.
    $registry->addTypeResolver(
      $this->getUnionTypeSdl(),
      function (?EntityInterface $value) use ($class) {
        if (!is_a($value, $class, TRUE)) {
          throw new UserError(sprintf('Could not resolve union entity type %s', $class));
        }

        $bundle = $this->getBundle($value->bundle());
        if (!$bundle) {
          throw new UserError(sprintf('Could not resolve union entity bundle %s::%s, is it enabled?', $class, $value->bundle()));
        }

        return $bundle->getTypeSdl();
      }
    );
  }

  /**
   * Resolve bundle types for the schema.
   *
   * @param \Drupal\graphql\GraphQL\ResolverRegistryInterface $registry
   *   The resolver registry.
   * @param \Drupal\graphql\GraphQL\ResolverBuilder $builder
   *   The resolver builder.
   * @param \Drupal\graphql_compose\Wrapper\EntityTypeWrapper $bundle
   *   The bundle to resolve.
   */
  protected function resolveBundleTypes(ResolverRegistryInterface $registry, ResolverBuilder $builder, EntityTypeWrapper $bundle): void {

    // The expected class for the entity type.
    $class = $this->entityTypeManager
      ->getDefinition($this->getEntityTypeId())
      ->getClass();

    $registry->addTypeResolver(
      $bundle->getTypeSdl(),
      function (?EntityInterface $value) use ($class) {
        if (!is_a($value, $class, TRUE)) {
          throw new UserError(sprintf('Could not resolve entity type %s', $class));
        }
        return $this->getBundle($value->bundle())->getTypeSdl();
      }
    );

    // Add fields to bundle type.
    $fields = $this->gqlFieldTypeManager->getBundleFields(
      $this->getEntityTypeId(),
      $bundle->getEntity()->id()
    );

    foreach ($fields as $field_plugin) {
      $registry->addFieldResolver(
        $bundle->getTypeSdl(),
        $field_plugin->getNameSdl(),
        $builder->produce('field_results')
          ->map('entity', $builder->fromParent())
          ->map('plugin', $builder->fromValue($field_plugin))
          ->map('value', $field_plugin->getProducers($builder)),
      );
    }
  }

  /**
   * Resolve bundle queries for the schema.
   *
   * @param \Drupal\graphql\GraphQL\ResolverRegistryInterface $registry
   *   The resolver registry.
   * @param \Drupal\graphql\GraphQL\ResolverBuilder $builder
   *   The resolver builder.
   * @param \Drupal\graphql_compose\Wrapper\EntityTypeWrapper $bundle
   *   The bundle to resolve.
   */
  protected function resolveBundleQueries(ResolverRegistryInterface $registry, ResolverBuilder $builder, EntityTypeWrapper $bundle): void {
    if (!$this->isQueryLoadSimple() && $bundle->isQueryLoadEnabled()) {
      $registry->addFieldResolver(
        'Query',
        $bundle->getNameSdl(),
        $builder->compose(
          $builder->produce('entity_load_by_uuid_or_id')
            ->map('type', $builder->fromValue($this->getEntityTypeId()))
            ->map('bundles', $builder->fromValue([$bundle->getEntity()->id()]))
            ->map('identifier', $builder->fromArgument('id'))
            ->map('language', $builder->fromArgument('langcode')),
          $builder->produce('entity_load_revision')
            ->map('entity', $builder->fromParent())
            ->map('identifier', $builder->fromArgument('revision'))
            ->map('language', $builder->fromArgument('langcode'))
        )
      );
    }
  }

  /**
   * Resolve bundle field unions for the schema.
   *
   * @param \Drupal\graphql\GraphQL\ResolverRegistryInterface $registry
   *   The resolver registry.
   * @param \Drupal\graphql\GraphQL\ResolverBuilder $builder
   *   The resolver builder.
   * @param \Drupal\graphql_compose\Wrapper\EntityTypeWrapper $bundle
   *   The bundle to register.
   */
  protected function resolveBundleFieldUnions(ResolverRegistryInterface $registry, ResolverBuilder $builder, EntityTypeWrapper $bundle): void {

    // Get the bundle fields.
    $fields = $this->gqlFieldTypeManager->getBundleFields(
      $this->getEntityTypeId(),
      $bundle->getEntity()->id()
    );

    // Add union field resolution for non-simple unions.
    foreach ($fields as $field_plugin) {
      // Check it uses the union trait.
      if (!$field_plugin instanceof FieldUnionInterface) {
        continue;
      }

      // Generic unions return a generic entity union.
      // Single unions just return the type.
      if ($field_plugin->isGenericUnion() || $field_plugin->isSingleUnion()) {
        continue;
      }

      $registry->addTypeResolver(
        $field_plugin->getUnionTypeSdl(),
        function (?EntityInterface $value) use ($field_plugin) {
          $entity_type_id = $value?->getEntityTypeId();
          $entity_bundle_id = $value?->bundle();

          $union_map = $entity_type_id . ':' . $entity_bundle_id;
          $union_mapping = $field_plugin->getUnionTypeMapping();

          if (array_key_exists($union_map, $union_mapping)) {
            return $union_mapping[$union_map];
          }

          throw new UserError(sprintf('Could not resolve union mapping %s:%s', $entity_type_id, $entity_bundle_id));
        }
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isQueryLoadSimple(): bool {
    $config = $this->configFactory->get('graphql_compose.settings');

    return $config->get('settings.simple_queries') ?: FALSE;
  }

}
