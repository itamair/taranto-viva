<?php

declare(strict_types=1);

namespace Drupal\search_api_typesense;

use Drupal\ai\AiProviderPluginManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\key\KeyRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Provides AI models for embedding and conversation.
 */
class AiModels {

  /**
   * The supported AI providers.
   *
   * @var array<string>
   */
  private array $supportedAiProviders = ['openai', 'litellm'];

  /**
   * AiModels constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\ai\AiProviderPluginManager|null $aiProviderPluginManager
   *   The AI provider plugin manager.
   * @param \Drupal\key\KeyRepositoryInterface|null $keyRepository
   *   The key repository.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerInterface $logger,
    private readonly ?AiProviderPluginManager $aiProviderPluginManager = NULL,
    private readonly ?KeyRepositoryInterface $keyRepository = NULL,
  ) {}

  /**
   * Check if AI support is available.
   *
   * @return bool
   *   TRUE if AI support is available, FALSE otherwise.
   */
  public function isAiSupportAvailable(): bool {
    return $this->aiProviderPluginManager !== NULL;
  }

  /**
   * Get the available embedding models.
   *
   * @return array<string>
   *   The available embedding models.
   */
  public function getEmbeddingModels(): array {
    return \array_merge(
      $this->adaptAiModuleModels('embeddings'),
      $this->getDefaultEmbeddingModels(),
    );
  }

  /**
   * Get the configuration for an embedding model.
   *
   * @param string|null $embedding_model
   *   The embedding model.
   *
   * @return array<string, string>
   *   The configuration.
   */
  public function getEmbeddingModelConfig(?string $embedding_model): array {
    if ($embedding_model === NULL) {
      return [];
    }

    try {
      [$provider_id, $model_id] = \explode('/', $embedding_model);

      if ($provider_id === 'ts') {
        return [
          'model_name' => \sprintf('%s/%s', $provider_id, $model_id),
        ];
      }

      $provider_config = $this->loadProviderConfig($provider_id);

      // For Typesense point of view, LiteLLM is an OpenAI-compatible
      // provider, but on a different host.
      if ($provider_id === 'litellm') {
        return [
          'model_name' => \sprintf('openai/%s', $model_id),
          'api_key' => $this->getKeyValue($provider_config),
          'url' => $provider_config->get('host'),
        ];
      }

      $embedding_config = [
        'model_name' => \sprintf('%s/%s', $provider_id, $model_id),
        'api_key' => $this->getKeyValue($provider_config),
      ];

      $url = $provider_config->get('url');
      if (\is_string($url)) {
        $embedding_config['url'] = $url;
      }

      return $embedding_config;
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());

      return [];
    }
  }

  /**
   * Get the available conversation models.
   *
   * @return array<string>
   *   The available conversation models.
   */
  public function getConversationModels(): array {
    return $this->adaptAiModuleModels('chat');
  }

  /**
   * Get the configuration for a conversation model.
   *
   * @param string|null $conversation_model
   *   The conversation model.
   *
   * @return array<string, string>
   *   The configuration.
   */
  public function getConversationModelConfig(
    ?string $conversation_model,
  ): array {
    if ($conversation_model === NULL) {
      return [];
    }

    try {
      [$provider_id, $model_id] = \explode('/', $conversation_model);

      $provider_config = $this->loadProviderConfig($provider_id);

      return [
        'api_key' => $this->getKeyValue($provider_config),
      ];
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());

      return [];
    }
  }

  /**
   * Adapt models from AI providers.
   *
   * @param string $operation_type
   *   The operation type.
   *
   * @return array<string>
   *   The available AI module models.
   */
  private function adaptAiModuleModels(string $operation_type): array {
    if (!$this->isAiSupportAvailable()) {
      return [];
    }

    $ai_provider_plugin_manager = $this->aiProviderPluginManager;
    if ($ai_provider_plugin_manager === NULL) {
      return [];
    }

    try {
      $models = [];
      $providers = $ai_provider_plugin_manager
        ->getProvidersForOperationType(
          $operation_type,
        );

      foreach ($providers as $provider) {
        $provider_configured_models = $this
          ->loadProviderConfiguredModels(
            $provider['id'],
            $operation_type,
          );

        foreach ($provider_configured_models as $model_id => $model_label) {
          $models[\sprintf('%s/%s', $provider['id'], $model_id)] = \sprintf(
            '%s: %s',
            $provider['id'],
            $model_label,
          );
        }
      }

      return $this->filterSupportedProvider($models);
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());

      return [];
    }
  }

  /**
   * Return default embedding models from the configuration.
   *
   * @return array<string>
   *   The default embedding models.
   */
  private function getDefaultEmbeddingModels(): array {
    $ts_embedding_models = $this
      ->configFactory
      ->get('search_api_typesense.settings')
      ->get('ts_embedding_models') ?? [];

    $models = [];
    foreach ($ts_embedding_models as $model) {
      [$provider_id, $model_id] = \explode('/', $model);

      $models[\sprintf('%s/%s', $provider_id, $model_id)] = \sprintf(
        '%s: %s',
        $provider_id,
        $model_id,
      );
    }

    return $models;
  }

  /**
   * Extract the key value from the configuration.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The configuration.
   *
   * @return string
   *   The key value.
   */
  private function getKeyValue(ImmutableConfig $config): string {
    $key = $this
      ->keyRepository?->getKey($config->get('api_key'));

    return $key?->getKeyValue() ?? '';
  }

  /**
   * Filter out providers that are not supported by the adapter.
   *
   * @param array<string> $models
   *   The providers to filter.
   *
   * @return array<string>
   *   The supported providers.
   */
  private function filterSupportedProvider(array $models): array {
    return \array_filter($models, function ($model_id) {
      [$provider_id, $model_name] = \explode('/', $model_id);

      return \in_array($provider_id, $this->supportedAiProviders, TRUE);
    }, ARRAY_FILTER_USE_KEY);
  }

  /**
   * @throws \InvalidArgumentException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\ai\Exception\AiOperationTypeMissingException
   */
  private function loadProviderConfig(string $provider_id): ImmutableConfig {
    $ai_provider_plugin_manager = $this->aiProviderPluginManager;
    if ($ai_provider_plugin_manager === NULL) {
      throw new \InvalidArgumentException(
        'AI provider plugin manager is not available.',
      );
    }

    $provider_loaded = $ai_provider_plugin_manager
      ->createInstance($provider_id);

    // @phpstan-ignore-next-line method.notFound
    return $provider_loaded->getConfig();
  }

  /**
   * @return array<string>
   *   The list of models.
   *
   * @throws \InvalidArgumentException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\ai\Exception\AiOperationTypeMissingException
   */
  private function loadProviderConfiguredModels(
    string $provider_id,
    string $operation_type,
  ): array {
    $ai_provider_plugin_manager = $this->aiProviderPluginManager;
    if ($ai_provider_plugin_manager === NULL) {
      throw new \InvalidArgumentException(
        'AI provider plugin manager is not available.',
      );
    }

    $provider_loaded = $ai_provider_plugin_manager
      ->createInstance($provider_id);

    // @phpstan-ignore-next-line method.notFound
    return $provider_loaded->getConfiguredModels($operation_type);
  }

}
