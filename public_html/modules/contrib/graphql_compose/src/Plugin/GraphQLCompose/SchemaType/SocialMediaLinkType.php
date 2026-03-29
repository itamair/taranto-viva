<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQLCompose\SchemaType;

use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeSchemaTypeBase;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeSchemaType(
 *   id = "SocialMediaLink",
 * )
 */
class SocialMediaLinkType extends GraphQLComposeSchemaTypeBase {

  /**
   * {@inheritdoc}
   */
  public function getTypes(): array {
    $types = [];

    if (!$this->moduleHandler->moduleExists('social_media_links_field')) {
      return $types;
    }

    $types[] = new ObjectType([
      'name' => $this->getPluginId(),
      'description' => (string) $this->t('A social media link.'),
      'fields' => fn() => [
        'id' => [
          'type' => Type::nonNull(Type::id()),
          'description' => (string) $this->t('Social media provider identifier'),
        ],
        'name' => [
          'type' => Type::nonNull(Type::string()),
          'description' => (string) $this->t('Social media provider name'),
        ],
        'value' => [
          'type' => Type::string(),
          'description' => (string) $this->t('Social media link value'),
        ],
        'url' => [
          'type' => Type::nonNull(Type::string()),
          'description' => (string) $this->t('Social media link URL'),
        ],
        'weight' => [
          'type' => Type::nonNull(Type::int()),
          'description' => (string) $this->t('Weight'),
        ],
        'description' => [
          'type' => Type::string(),
          'description' => (string) $this->t('Description'),
        ],
      ],
    ]);

    return $types;
  }

}
