<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_layout_paragraphs\Plugin\GraphQL\SchemaExtension;

use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistryInterface;
use Drupal\graphql_compose\Plugin\GraphQL\SchemaExtension\ResolverOnlySchemaExtensionPluginBase;

/**
 * CaaS GraphQL Extension.
 *
 * @SchemaExtension(
 *   id = "graphql_compose_layout_paragraphs",
 *   name = "GraphQL Compose Layout Paragraphs",
 *   description = @Translation("Layout entities"),
 *   schema = "graphql_compose",
 * )
 */
class LayoutParagraphsSchemaExtension extends ResolverOnlySchemaExtensionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function registerResolvers(ResolverRegistryInterface $registry): void {
    $builder = new ResolverBuilder();
    $paragraph_plugin = $this->gqlEntityTypeManager->getPluginInstance('paragraph');

    foreach ($paragraph_plugin->getBundles() as $bundle) {
      $registry->addFieldResolver(
        $bundle->getTypeSdl(),
        'composition',
        $builder->produce('layout_paragraphs')
          ->map('entity', $builder->fromParent())
      );
    }
  }

}
