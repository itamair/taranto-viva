<?php

namespace Drupal\ai_provider_litellm\Plugin\AiProvider;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\OpenAiBasedProviderClientBase;
use Drupal\ai\Exception\AiQuotaException;
use Drupal\ai\Exception\AiRateLimitException;
use Drupal\ai\Exception\AiSetupFailureException;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;
use Drupal\ai_provider_litellm\LiteLLM\LiteLlmAiClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'LiteLLM Proxy' provider.
 */
#[AiProvider(
  id: 'litellm',
  label: new TranslatableMarkup('LiteLLM Proxy'),
)]
class LiteLlmAiProvider extends OpenAiBasedProviderClientBase {

  /**
   * The LiteLLM API client.
   *
   * @var \Drupal\ai_provider_litellm\LiteLLM\LiteLlmAiClient
   */
  protected LiteLlmAiClient $liteLlmClient;

  /**
   * The AI cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $aiCache;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $parent_instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $parent_instance->aiCache = $container->get('cache.ai');
    return $parent_instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function loadClient(): void {
    $config = $this->getConfig();
    $this->liteLlmClient = new LiteLlmAiClient(
      $this->httpClient,
      $this->keyRepository,
      $config->get('host'),
      $config->get('api_key'),
    );

    // Set custom endpoint from host config if available.
    if (!empty($this->getConfig()->get('host'))) {
      $this->setEndpoint($this->getConfig()->get('host'));
    }

    try {
      parent::loadClient();
    }
    catch (AiSetupFailureException $e) {
      throw new AiSetupFailureException('Failed to initialize LiteLLM client: ' . $e->getMessage(), $e->getCode(), $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array {
    // Load all models, and since OpenAI does not provide information about
    // which models does what, we need to hard code it in a helper function.
    $this->loadClient();
    return $this->getModels($operation_type ?? '', $capabilities);
  }

  /**
   * Retrieves and filters a list of models from the LiteLLM client.
   *
   * Filters out deprecated or unsupported models based on the operation type.
   * The LiteLLM API does not natively filter these models.
   *
   * @param string $operation_type
   *   The bundle to filter models by.
   * @param array $capabilities
   *   The capabilities to filter models by.
   *
   * @return array
   *   A filtered list of public models.
   */
  public function getModels(string $operation_type, array $capabilities): array {
    $models = [];
    foreach ($this->liteLlmClient->models() as $model) {
      switch ($operation_type) {
        case 'text_to_image':
          if ($model->supportsImageOutput) {
            $models[$model->name] = $model->name;
          }
          break;

        case 'text_to_speech':
          if ($model->supportsAudioOutput) {
            $models[$model->name] = $model->name;
          }
          break;

        case 'audio_to_audio':
          if ($model->supportsAudioInput && $model->supportsAudioOutput) {
            $models[$model->name] = $model->name;
          }
          break;

        case 'moderation':
          if ($model->supportsModeration) {
            $models[$model->name] = $model->name;
          }
          break;

        case 'embeddings':
          if ($model->supportsEmbeddings) {
            $models[$model->name] = $model->name;
          }
          break;

        case 'chat':
          if ($model->supportsChat) {
            $models[$model->name] = $model->name;
          }
          break;

        case 'image_and_audio_to_video':
          if ($model->supportsImageAndAudioToVideo) {
            $models[$model->name] = $model->name;
          }
          break;

        default:
          break;
      }
    }
    return $models;
  }

  /**
   * {@inheritdoc}
   */
  public function getApiDefinition(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSetupData(): array {
    // Don't set up any default models.
    return [
      'key_config_name' => 'api_key',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function postSetup(): void {
    // Prevent the OpenAI rate limit check.
  }

  /**
   * {@inheritdoc}
   */
  public function embeddingsVectorSize(string $model_id): int {
    // Since we don't have the size, we need to calculate it.
    $cid = 'embeddings_size:' . $this->getPluginId() . ':' . $model_id;
    if ($cached = $this->aiCache->get($cid)) {
      return $cached->data;
    }

    // Just until all providers have the trait.
    if (!method_exists($this, 'embeddings')) {
      return 0;
    }
    // Normalize the input.
    $input = new EmbeddingsInput('Hello world!');
    $embedding = $this->embeddings($input, $model_id);
    try {
      $size = count($embedding->getNormalized());
    }
    catch (\Exception $e) {
      return 0;
    }
    $this->aiCache->set($cid, $size);

    return $size;
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    $this->loadClient();
    $model_info = $this->liteLlmClient->models()[$model_id] ?? NULL;

    if (!$model_info) {
      return $generalConfig;
    }

    foreach (array_keys($generalConfig) as $name) {
      if (!in_array($name, $model_info->supportedOpenAiParams)) {
        unset($generalConfig[$name]);
      }
    }

    return $generalConfig;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    return [
      'audio_to_audio',
      'chat',
      'embeddings',
      'moderation',
      'text_to_image',
      'text_to_speech',
      'image_and_audio_to_video',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function handleApiException(\Exception $e): void {
    if (strpos($e->getMessage(), 'Request too large') !== FALSE || strpos($e->getMessage(), 'Too Many Requests') !== FALSE) {
      throw new AiRateLimitException($e->getMessage());
    }
    if (strpos($e->getMessage(), 'Budget has been exceeded') !== FALSE) {
      throw new AiQuotaException($e->getMessage());
    }
    throw $e;
  }

}
