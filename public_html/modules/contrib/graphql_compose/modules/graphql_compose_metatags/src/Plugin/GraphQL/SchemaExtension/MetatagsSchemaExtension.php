<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_metatags\Plugin\GraphQL\SchemaExtension;

use Drupal\graphql\GraphQL\ResolverRegistryInterface;
use Drupal\graphql_compose\Plugin\GraphQL\SchemaExtension\ResolverOnlySchemaExtensionPluginBase;
use GraphQL\Error\UserError;

/**
 * Adds Entity Type GraphQL Compose plugins to the GraphQL API.
 *
 * @SchemaExtension(
 *   id = "graphql_compose_metatags",
 *   name = "GraphQL Compose Metatags",
 *   description = @Translation("Add metatag information to the Schema."),
 *   schema = "graphql_compose",
 * )
 */
class MetatagsSchemaExtension extends ResolverOnlySchemaExtensionPluginBase {

  /**
   * {@inheritdoc}
   *
   * @throws \GraphQL\Error\UserError
   *   Thrown when the type cannot be resolved.
   */
  public function registerResolvers(ResolverRegistryInterface $registry): void {
    $registry->addTypeResolver(
      'MetaTagUnion',
      function ($value) {
        $type = NULL;

        // The tag attr is a convenient way to determine the type of meta tag.
        $tag = $value['tag'] ?? NULL;
        $attributes = $value['attributes'] ?? [];

        if ($tag === 'link') {
          $type = 'MetaTagLink';
        }
        elseif ($tag === 'script') {
          $type = 'MetaTagScript';
        }
        elseif ($tag === 'meta' && array_key_exists('name', $attributes)) {
          $type = 'MetaTagValue';
        }
        elseif ($tag === 'meta' && array_key_exists('property', $attributes)) {
          $type = 'MetaTagProperty';
        }

        // Give opportunity to extend this union.
        $this->moduleHandler->invokeAll('graphql_compose_metatags_union_alter', [
          $value,
          &$type,
        ]);

        if (!$type) {
          throw new UserError('Could not resolve type for meta tag value.');
        }

        return $type;
      }
    );
  }

}
