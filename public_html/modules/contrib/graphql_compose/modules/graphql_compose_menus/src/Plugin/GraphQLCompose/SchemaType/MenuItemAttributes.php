<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_menus\Plugin\GraphQLCompose\SchemaType;

use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeSchemaTypeBase;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Symfony\Component\DependencyInjection\ContainerInterface;
use function Symfony\Component\String\u;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeSchemaType(
 *   id = "MenuItemAttributes",
 * )
 */
class MenuItemAttributes extends GraphQLComposeSchemaTypeBase {

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

    $types[] = new ObjectType([
      'name' => $this->getPluginId(),
      'description' => (string) $this->t('Menu item options set within the CMS.'),
      'fields' => function () {

        $fields = [];

        // Always provide the class attribute.
        $definitions = [
          'class' => [],
        ];

        // Add module-provided attributes.
        if ($this->moduleHandler->moduleExists('menu_link_attributes')) {
          $definitions = array_merge(
            $definitions,
            $this->configFactory->get('menu_link_attributes.config')->get('attributes') ?: []
          );
        }
        elseif ($this->moduleHandler->moduleExists('link_attributes_menu_link_content')) {
          $definitions = array_merge(
            $definitions,
            $this->linkAttributesManager->getDefinitions()
          );
        }

        /** @var array<string,array> $definitions */
        foreach ($definitions as $id => $attribute) {
          $description = $attribute['description'] ?? $attribute['title'] ?? NULL;

          $fields[u($id)->camel()->toString()] = [
            'type' => Type::string(),
            'description' => (string) ($description ?: $this->t('Menu item attribute @id.', ['@id' => $id])),
          ];
        }

        ksort($fields);

        return $fields;
      },
    ]);

    return $types;
  }

}
