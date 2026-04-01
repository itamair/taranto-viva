<?php

declare(strict_types=1);

namespace Drupal\graphql_compose\Plugin\GraphQL\Schema;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\graphql\Plugin\GraphQL\Schema\AlterableComposableSchema;

/**
 * The provider of the schema base for the GraphQL Compose GraphQL API.
 *
 * Provides a target schema for GraphQL Schema extensions. Schema Extensions
 * should implement `SchemaExtensionPluginInterface` and should not subclass
 * this class.
 *
 * @Schema(
 *   id = "graphql_compose",
 *   name = "GraphQL Compose schema",
 * )
 *
 * @internal
 */
class GraphQLComposeSchema extends AlterableComposableSchema {

  use StringTranslationTrait;
  use MessengerTrait;

  /**
   * {@inheritDoc}
   *
   * - Load all extensions that are tagged with graphql_compose.
   */
  public function defaultConfiguration() {
    $extensions = $this->extensionManager->getDefinitions();
    $extensions = array_filter($extensions, function ($definition) {
      return $definition['schema'] === 'graphql_compose';
    });

    return ['extensions' => array_keys($extensions)];
  }

  /**
   * {@inheritdoc}
   */
  protected function getSchemaDefinition() {
    return <<<GQL
      # GraphQL Compose
      schema {
        query: Query
        mutation: Mutation
        subscription: Subscription
      }

      """
      The schema's entry-point for queries.
      """
      type Query

      """
      The schema's entry-point for mutations.
      """
      type Mutation {
        """
        Placeholder for mutation extension.
        """
        _: Boolean!
      }

      """
      The schema's entry-point for subscriptions.
      """
      type Subscription {
        """
        Placeholder for subscription extension.
        """
        _: Boolean!
      }

    GQL;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $info = $form_state->getBuildInfo();

    if ($info['form_id'] === 'graphql_server_create_form') {
      $this->messenger()->addStatus('GraphQL Compose is ready to use.');
      $form['settings_good'] = [
        '#type' => 'status_messages',
        '#display' => 'status',
      ];
    }
    else {
      $form['settings_link'] = [
        '#type' => 'link',
        '#title' => $this->t('Configure GraphQL Compose schema'),
        '#url' => Url::fromRoute('graphql_compose.schema'),
        '#attributes' => [
          'class' => ['button'],
        ],
      ];
    }

    $form['enabled'] = [
      '#type' => 'hidden',
      '#value' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // Satisfy interface. Nothing to do here.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // Do nothing. We autoload all extensions.
  }

}
