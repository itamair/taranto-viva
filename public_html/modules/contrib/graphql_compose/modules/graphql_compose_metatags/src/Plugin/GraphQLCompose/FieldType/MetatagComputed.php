<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_metatags\Plugin\GraphQLCompose\FieldType;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\TypedData\TypedDataTrait;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerItemsInterface;
use Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerTrait;
use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeFieldTypeBase;
use Drupal\schema_metatag\SchemaMetatagManager;
use Drupal\typed_data\DataFetcherTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeFieldType(
 *   id = "metatag_computed",
 *   type_sdl = "MetaTagUnion",
 * )
 */
class MetatagComputed extends GraphQLComposeFieldTypeBase implements FieldProducerItemsInterface, ContainerFactoryPluginInterface {

  use TypedDataTrait;
  use DataFetcherTrait;
  use FieldProducerTrait;

  /**
   * Drupal renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

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

    $instance->renderer = $container->get('renderer');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function resolveFieldItems(FieldItemListInterface $field, FieldContext $context): array {

    $bubbleable = new BubbleableMetadata();
    $render_context = new RenderContext();

    $manager = $this->getTypedDataManager();

    $type = 'entity:' . $field->getFieldDefinition()->getTargetEntityTypeId();
    $definition = $manager->createDataDefinition($type);
    $typed_data = $manager->create($definition, $field->getEntity());

    $metatags = $this->renderer->executeInRenderContext(
      $render_context,
      function () use ($typed_data, $bubbleable) {
        return $this->getDataFetcher()
          ->fetchDataByPropertyPath($typed_data, 'metatag', $bubbleable)
          ->getValue();
      }
    );

    if (!$render_context->isEmpty()) {
      $context->addCacheableDependency($render_context->pop());
    }

    $metatags = $metatags ?: [];

    $this->addSchemaMetatags($metatags);

    return $metatags;
  }

  /**
   * Add schema metatags to the list.
   *
   * @param array $metatags
   *   The list of metatags.
   */
  protected function addSchemaMetatags(array &$metatags) {
    if (!$this->moduleHandler->moduleExists('schema_metatag')) {
      return;
    }

    // Find the schema metatags in the list.
    $schema_metatags = array_filter($metatags,
      fn ($item) => !empty($item['attributes']['schema_metatag'])
    );

    if (empty($schema_metatags)) {
      return;
    }

    // Reduce the schema metatags from the list.
    $metatags = array_diff_key($metatags, $schema_metatags);

    // Change the data to match the manager structure.
    $schema_metatags = array_map(
      fn ($item) => [['#attributes' => $item['attributes']]],
      $schema_metatags
    );

    // Add back in the combined schema json+ld metatag.
    $content = SchemaMetatagManager::parseJsonld($schema_metatags);

    $metatags[] = [
      'tag' => 'script',
      'content' => SchemaMetatagManager::encodeJsonld($content),
      'attributes' => [
        'type' => 'application/ld+json',
      ],
    ];
  }

}
