<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQLCompose\FieldType;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerItemInterface;
use Drupal\graphql_compose\Plugin\GraphQL\DataProducer\FieldProducerTrait;
use Drupal\graphql_compose\Plugin\GraphQLCompose\GraphQLComposeFieldTypeBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * {@inheritdoc}
 *
 * @GraphQLComposeFieldType(
 *   id = "file",
 *   type_sdl = "File",
 * )
 */
class FileItem extends GraphQLComposeFieldTypeBase implements FieldProducerItemInterface, ContainerFactoryPluginInterface {

  use FieldProducerTrait;

  /**
   * File URL generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

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

    $instance->fileUrlGenerator = $container->get('file_url_generator');
    $instance->renderer = $container->get('renderer');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function resolveFieldItem(FieldItemInterface $item, FieldContext $context) {
    if (!$item->entity) {
      return NULL;
    }

    $access = $item->entity->access('view', NULL, TRUE);
    $context->addCacheableDependency($access);

    if (!$access->isAllowed()) {
      return NULL;
    }

    /** @var \Drupal\file\FileInterface $file */
    $file = $item->entity;

    $render_context = new RenderContext();
    $url = $this->renderer->executeInRenderContext($render_context, function () use ($file) {
      return $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
    });

    if (!$render_context->isEmpty()) {
      $context->addCacheableDependency($render_context->pop());
    }

    $context->addCacheableDependency($file);

    return [
      'url' => $url,
      'name' => $file->getFilename(),
      'size' => (int) $file->getSize(),
      'mime' => $file->getMimeType(),
      'description' => $item->description ?: NULL,
    ];
  }

}
