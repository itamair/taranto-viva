<?php

declare(strict_types=1);

namespace Drupal\graphql_compose_comments\Plugin\GraphQL\DataProducer;

use Drupal\Component\Utility\Xss;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\comment\CommentInterface;
use Drupal\comment\Entity\Comment;
use Drupal\graphql\GraphQL\Execution\FieldContext;
use Drupal\graphql\Plugin\GraphQL\DataProducer\DataProducerPluginBase;
use Drupal\graphql_compose_comments\CommentableTrait;
use GraphQL\Error\UserError;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a new comment entity.
 *
 * @DataProducer(
 *   id = "create_comment",
 *   name = @Translation("Create Comment"),
 *   description = @Translation("Creates a new comment."),
 *   produces = @ContextDefinition("any",
 *     label = @Translation("Comment"),
 *   ),
 *   consumes = {
 *     "data" = @ContextDefinition("any",
 *       label = @Translation("Comment data"),
 *     ),
 *     "entity" = @ContextDefinition("any",
 *       label = @Translation("Entity to comment on"),
 *     ),
 *   },
 * )
 */
class CreateComment extends DataProducerPluginBase implements ContainerFactoryPluginInterface {

  use CommentableTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
    );

    $instance->currentUser = $container->get('current_user');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityFieldManager = $container->get('entity_field.manager');

    return $instance;
  }

  /**
   * Creates a comment.
   *
   * @param array $data
   *   The comment data.
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The entity to comment on.
   * @param \Drupal\graphql\GraphQL\Execution\FieldContext $context
   *   The cache context.
   *
   * @return \Drupal\comment\CommentInterface|null
   *   The created comment.
   *
   * @throws \GraphQL\Error\UserError
   *   First error violation returned from the comment entity validation.
   */
  public function resolve(array $data, ?EntityInterface $entity, FieldContext $context): ?CommentInterface {

    if (!$entity) {
      return NULL;
    }

    if (!$this->currentUser->hasPermission('post comments')) {
      $context->addCacheableDependency($this->currentUser);
      throw new UserError('You do not have permission to post comments.');
    }

    $field_definitions = $this->entityFieldManager->getFieldDefinitions(
      $entity->getEntityTypeId(),
      $entity->bundle()
    );

    // Get the first comment field or the user defined one.
    $field_definition = NULL;
    $field_name = $data['entityField'] ?? NULL;

    if ($field_name) {
      $field_definition = $field_definitions[$field_name] ?? NULL;
    }
    else {
      foreach ($field_definitions as $definition) {
        if ($definition->getType() === 'comment') {
          $field_definition = $definition;
          break;
        }
      }
    }

    if (!$field_definition) {
      return NULL;
    }

    $payload = [
      'comment_type' => $field_definition->getSetting('comment_type'),
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
      'field_name' => $field_definition->getName(),
      'subject' => Xss::filter($data['subject'] ?? ''),
      'homepage' => Xss::filter($data['homepage'] ?? ''),
    ];

    // Auto-fill the author if the user is authenticated.
    if ($this->currentUser->isAuthenticated()) {
      $payload['uid'] = $this->currentUser->id();
    }
    else {
      $payload['name'] = Xss::filter($data['name'] ?? '');
      $payload['mail'] = Xss::filter($data['mail'] ?? '');
    }

    // Allow threading or replies.
    if (!empty($data['replyTo'])) {
      if (Uuid::isValid($data['replyTo'])) {
        // Reply to a comment by UUID.
        $reply_entity = $this->entityTypeManager
          ->getStorage('comment')
          ->loadByProperties(['uuid' => $data['replyTo']]);

        $payload['pid'] = $reply_entity ? key($reply_entity) : NULL;
      }
      else {
        // Reply to a comment by ID.
        $payload['pid'] = (int) $data['replyTo'];
      }
    }

    // Create the comment.
    $comment = Comment::create($payload);

    // Add our user defined content.
    $input_fields = $this->getInputFields('comment', $field_definition->getSetting('comment_type'));
    foreach ($input_fields as $sdl_name => $input_field) {
      if (!empty($data[$sdl_name])) {
        $input_field_name = $input_field['definition']->getName();
        $value = Xss::filter($data[$sdl_name]);
        $comment->{$input_field_name}->setValue($value);
      }
    }

    // Validate the entity.
    $violations = $comment->validate();
    if ($violations->count() > 0) {
      throw new UserError((string) $violations->get(0)->getMessage());
    }

    $comment->save();

    return $comment;
  }

}
