<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQLCompose\FieldType;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterPluginManager;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerItemsInterface;
use Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerTrait;
use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeFieldTypeBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeFieldType(
 *   id = "social_media_links_field",
 *   type_sdl = "SocialMediaLink",
 * )
 */
class SocialMediaLinksFieldItem extends GraphQLComposeFieldTypeBase implements FieldProducerItemsInterface {

  use FieldProducerTrait;

  /**
   * Field formatter plugin manager.
   *
   * @var \Drupal\Core\Field\FormatterPluginManager
   */
  protected FormatterPluginManager $fieldFormatterPluginManager;

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

    $instance->fieldFormatterPluginManager = $container->get('plugin.manager.field.formatter');

    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * Contrib module has a unique structure.
   *
   * It's designed to store multiple social media links.
   * And not be multiple instances of the same provider.
   *
   * @see https://www.drupal.org/project/social_media_links/issues/3013775
   */
  public function isMultiple(): bool {
    return TRUE;
  }

  /**
   * Hijack the field formatter to get the enabled items.
   *
   * This takes care of converting between the contrib widgets.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field to process.
   *
   * @return array
   *   The resolved provider instances.
   *
   * @see SocialMediaLinksFieldDefaultFormatter
   */
  protected function getPlatformsWithValues(FieldItemListInterface $field): array {
    $formatter = $this->fieldFormatterPluginManager
      ->createInstance('social_media_links_field_default', [
        'field_definition' => $field->getFieldDefinition(),
        'label' => 'hidden',
        'view_mode' => 'default',
        'settings' => [],
        'third_party_settings' => [],
      ]);

    $method = new \ReflectionMethod($formatter, 'getPlatformsWithValues');
    $method->setAccessible(TRUE);

    return $method->invoke($formatter, $field) ?: [];
  }

  /**
   * {@inheritdoc}
   */
  public function resolveFieldItems(FieldItemListInterface $field, FieldContext $context): array {
    $links = [];

    foreach ($this->getPlatformsWithValues($field) as $provider) {
      /** @var \Drupal\social_media_links\PlatformInterface $instance */
      $instance = $provider['instance'];

      $links[] = [
        'id' => $instance->getPluginId(),
        'name' => $instance->getName(),
        'value' => $instance->getValue(),
        'description' => $instance->getDescription(),
        'url' => $instance->generateUrl($instance->getUrl()),
        'weight' => (int) ($provider['weight'] ?? 0),
      ];
    }

    return $links;
  }

}
