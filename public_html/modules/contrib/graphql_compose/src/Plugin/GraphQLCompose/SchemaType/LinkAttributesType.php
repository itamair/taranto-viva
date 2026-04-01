<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQLCompose\SchemaType;

use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeSchemaTypeBase;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Symfony\Component\DependencyInjection\ContainerInterface;
use function Symfony\Component\String\u;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeSchemaType(
 *   id = "LinkAttributes",
 * )
 */
class LinkAttributesType extends GraphQLComposeSchemaTypeBase {

  /**
   * Link attributes plugin manager.
   *
   * @var \Drupal\link_attributes\LinkAttributesManager|null
   */
  protected $linkAttributesManager;

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

    $instance->linkAttributesManager = $container->get(
      'plugin.manager.link_attributes',
      ContainerInterface::NULL_ON_INVALID_REFERENCE
    );

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getTypes(): array {
    $types = [];

    if (!$this->moduleHandler->moduleExists('link_attributes')) {
      return $types;
    }

    $types[] = new ObjectType([
      'name' => $this->getPluginId(),
      'description' => (string) $this->t('Link item attributes set within the CMS.'),
      'fields' => function () {
        $fields = [];

         /** @var array<string,array> $definitions */
        $definitions = $this->linkAttributesManager->getDefinitions();

        foreach ($definitions as $id => $attribute) {
          $description = $attribute['description'] ?? $attribute['title'] ?? NULL;

          $fields[u($id)->camel()->toString()] = [
            'type' => Type::string(),
            'description' => (string) ($description ?: $this->t('Link attribute @id.', ['@id' => $id])),
          ];
        }

        ksort($fields);

        return $fields;
      },
    ]);

    return $types;
  }

  /**
   * {@inheritdoc}
   */
  public function getExtensions(): array {
    $extensions = parent::getExtensions();

    if (!$this->moduleHandler->moduleExists('link_attributes')) {
      return $extensions;
    }

    $extensions[] = new ObjectType([
      'name' => 'Link',
      'fields' => fn() => [
        'attributes' => [
          'type' => Type::getNullableType(static::type('LinkAttributes')),
          'description' => 'Link item attributes set within the CMS.',
        ],
      ],
    ]);

    return $extensions;
  }

}
