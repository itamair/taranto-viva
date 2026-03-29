<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_layout_builder\Plugin\GraphQL\SchemaExtension;

use Drupal\graphql\GraphQL\ResolverBuilder;
use Drupal\graphql\GraphQL\ResolverRegistryInterface;
use Drupal\graphql_compose\Plugin\GraphQL\SchemaExtension\ResolverOnlySchemaExtensionPluginBase;
use Drupal\graphql_compose_layout_builder\EnabledBundlesTrait;
use Drupal\graphql_compose_layout_builder\Wrapper\LayoutBuilderSection;
use Drupal\layout_builder\Plugin\Block\FieldBlock;
use Drupal\layout_builder\SectionComponent;

/**
 * Add layout builder extras to the Schema.
 *
 * @SchemaExtension(
 *   id = "graphql_compose_layout_builder",
 *   name = "GraphQL Compose Layout Builder",
 *   description = @Translation("Add layout builder extras to the Schema."),
 *   schema = "graphql_compose",
 * )
 */
class LayoutBuilderSchemaExtension extends ResolverOnlySchemaExtensionPluginBase {

  use EnabledBundlesTrait;

  /**
   * {@inheritdoc}
   */
  public function registerResolvers(ResolverRegistryInterface $registry): void {
    $builder = new ResolverBuilder();

    // Generate a key for this section.
    // This'll be required for layouts in react/vue.
    // Change to uuid once upstream is fixed.
    // @see https://www.drupal.org/project/drupal/issues/3208766
    $registry->addFieldResolver(
      'LayoutBuilderSection',
      'id',
      $builder->callback(fn (LayoutBuilderSection $section) => $section->id()),
    );

    $registry->addFieldResolver(
      'LayoutBuilderSection',
      'layout',
      $builder->callback(fn (LayoutBuilderSection $section) => $section->getLayoutId()),
    );

    // Unsure what to share here. Yolo into UntypedStructuredData.
    // @see https://www.drupal.org/node/2942975
    $registry->addFieldResolver(
      'LayoutBuilderSection',
      'settings',
      $builder->callback(function (LayoutBuilderSection $section) {
        $settings = $section->getLayoutSettings();

        // Remove administrative label.
        unset($settings['label']);
        unset($settings['context_mapping']);

        return $settings;
      }),
    );

    $registry->addFieldResolver(
      'LayoutBuilderSection',
      'weight',
      $builder->callback(fn (LayoutBuilderSection $section) => $section->getDelta()),
    );

    $registry->addFieldResolver(
      'LayoutBuilderSection',
      'components',
      $builder->callback(fn (LayoutBuilderSection $section) => $section->getComponents()),
    );

    $registry->addFieldResolver(
      'LayoutBuilderComponent',
      'id',
      $builder->callback(fn (SectionComponent $component) => $component->getUuid()),
    );

    $registry->addFieldResolver(
      'LayoutBuilderComponent',
      'region',
      $builder->callback(fn (SectionComponent $component) => $component->getRegion()),
    );

    $registry->addFieldResolver(
      'LayoutBuilderComponent',
      'weight',
      $builder->callback(fn (SectionComponent $component) => $component->getWeight()),
    );

    // Unsure what to share here. Yolo into UntypedStructuredData.
    // @see https://www.drupal.org/node/2942975
    $registry->addFieldResolver(
      'LayoutBuilderComponent',
      'configuration',
      $builder->callback(function (SectionComponent $component) {
        $settings = $component->get('configuration');

        unset($settings['context_mapping']);

        return $settings;
      }),
    );

    $registry->addFieldResolver(
      'LayoutBuilderComponent',
      'block',
      $builder->produce('section_component_field_block_load')
        ->map('component', $builder->fromParent())
        ->map('contexts', $builder->fromContext('layout_builder_contexts')),
    );

    // Block field.
    $registry->addFieldResolver(
      'BlockField',
      'field',
      $builder->fromParent()
    );

    // Block field name.
    $registry->addFieldResolver(
      'BlockField',
      'fieldName',
      $builder->callback(function (FieldBlock $block_instance) {
        $field = $this->getBlockFieldPluginInstance($block_instance);

        return $field ? $field->getNameSdl() : 'UnsupportedType';
      })
    );

    // Register the layout builder context field for any layout enabled entity.
    $bundles = $this->getEnabledBundlePlugins();

    foreach ($bundles as $bundle) {
      $registry->addFieldResolver(
        $bundle->getTypeSdl(),
        'sections',
        $builder->compose(
          $builder->produce('layout_builder_contexts')
            ->map('view_mode', $builder->fromArgument('viewMode'))
            ->map('entity', $builder->fromParent()),

          $builder->context('layout_builder_contexts', $builder->fromParent()),

          $builder->produce('layout_builder_sections')
            ->map('contexts', $builder->fromParent()),
        )
      );

      $fields = $this->gqlFieldTypeManager->getBundleFields(
        $bundle->getEntityTypePlugin()->getEntityTypeId(),
        $bundle->getEntity()->id()
      );

      foreach ($fields as $field_plugin) {
        $registry->addFieldResolver(
          $this->getLayoutBuilderFieldTypeSdl($field_plugin),
          $field_plugin->getNameSdl(),
          $builder->compose(
            // Get the entity off the block.
            $builder->produce('field_block_entity_load')
              ->map('block_instance', $builder->fromParent()),

            $builder->produce('field_results')
              ->map('entity', $builder->fromParent())
              ->map('plugin', $builder->fromValue($field_plugin))
              ->map('value', $field_plugin->getProducers($builder)),
          )
        );
      }
    }

    // Create a union resolver for the block instance.
    $registry->addTypeResolver(
      'BlockFieldUnion',
      function (FieldBlock $block_instance) {
        $field = $this->getBlockFieldPluginInstance($block_instance);

        return $field ? $this->getLayoutBuilderFieldTypeSdl($field) : 'UnsupportedType';
      });
  }

}
