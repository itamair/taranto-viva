<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_image_style\Plugin\GraphQL\SchemaExtension;

use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistryInterface;
use Drupal\graphql_compose\Plugin\GraphQL\SchemaExtension\ResolverOnlySchemaExtensionPluginBase;

/**
 * Add image styles to the Schema.
 *
 * @SchemaExtension(
 *   id = "graphql_compose_image_style",
 *   name = "GraphQL Compose Image Style",
 *   description = @Translation("Add image styles to the Schema."),
 *   schema = "graphql_compose",
 * )
 */
class ImageStyleSchemaExtension extends ResolverOnlySchemaExtensionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function registerResolvers(ResolverRegistryInterface $registry): void {
    $builder = new ResolverBuilder();

    // Add style() query to Image types.
    $registry->addFieldResolver(
      'Image',
      'variations',
      $builder->compose(
        // Get the field->entity file from the parent.
        $builder->produce('property_path')
          ->map('value', $builder->fromContext('field_value'))
          ->map('path', $builder->fromValue('entity')),

        // Get the image derivatives.
        $builder->produce('image_derivatives')
          ->map('entity', $builder->fromParent())
          ->map('styles', $builder->produce('schema_enum_value')
            ->map('type', $builder->fromValue('ImageStyleAvailable'))
            ->map('value', $builder->fromArgument('styles')),
          )
      ),
    );
  }

}
