<?php

namespace Drupal\custom_module\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Modify entity field values.
 */
#[Action(
  id: "views_bulk_operations_regenerate_ai_automators_fields",
  label: new TranslatableMarkup("Regenerate AI Automators fields"),
  type: "node"
)]
class RegenarateAiAutomatorsFields extends ViewsBulkOperationsActionBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new EntityDeleteAction object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   Logger channel for VBO.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly LoggerChannelInterface $logger,
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
      $container->get('logger.channel.views_bulk_operations')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?EntityInterface $entity = NULL): TranslatableMarkup {
    if ($entity === NULL) {
      return $this->t('No entity provided.');
    }

    if (!$entity instanceof TranslatableInterface || !$entity->hasTranslation('it')) {
      return $this->t('No Italian translation found for @label.', [
        '@label' => $entity->label(),
      ]);
    }

    $translation = $entity->getTranslation('it');

    $fields = [
      'body',
      'field_sub_title',
      'field_why_to_visit',
      'field_how_to_reach',
      'field_crawled_content',
      'field_more_info',
    ];
    foreach ($fields as $field) {
      if ($translation->hasField($field)) {
        $translation->set($field, NULL);
      }
    }
    $translation->save();

    return $this->t('Cleared AI Automator fields on Italian version of @label.', [
      '@label' => $entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $object->access('delete', $account, $return_as_object);
  }

}
