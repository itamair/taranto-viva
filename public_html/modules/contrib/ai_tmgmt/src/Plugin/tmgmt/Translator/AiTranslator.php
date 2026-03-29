<?php

namespace Drupal\ai_tmgmt\Plugin\tmgmt\Translator;

use Drupal\ai_translate\TranslationException;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\Utility\TextChunkerInterface;
use Drupal\ai\Utility\TokenizerInterface;
use Drupal\ai_tmgmt\Plugin\QueueWorker\AiTranslatorWorker;
use Drupal\tmgmt\ContinuousTranslatorInterface;
use Drupal\tmgmt\Data;
use Drupal\tmgmt\Entity\JobItem;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\JobItemInterface;
use Drupal\tmgmt\TMGMTException;
use Drupal\tmgmt\Translator\AvailableResult;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt\TranslatorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * AI translator plugin.
 *
 * @TranslatorPlugin(
 *   id = "ai",
 *   label = @Translation("AI"),
 *   description = @Translation("AI Translator service."),
 *   ui = "Drupal\ai_tmgmt\AiTranslatorUi",
 *   logo = "icons/ai-module-logo.jpg",
 * )
 */
class AiTranslator extends TranslatorPluginBase implements ContainerFactoryPluginInterface, ContinuousTranslatorInterface {

  /**
   * If the process is being run via cron or not.
   *
   * @var bool|null
   */
  protected ?bool $isCron = NULL;

  /**
   * Constructs the AI Translator.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\tmgmt\Data $dataHelper
   *   Data helper service.
   * @param \Drupal\ai\Utility\TokenizerInterface $tokenizer
   *   The tokenizer.
   * @param \Drupal\ai\Utility\TextChunkerInterface $textChunker
   *   The text chunker.
   * @param \Drupal\Core\Queue\QueueInterface $queue
   *   The queue object.
   * @param \Drupal\ai_tmgmt\Plugin\QueueWorker\AiTranslatorWorker $queueWorker
   *   The queue worker.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    protected LanguageManagerInterface $languageManager,
    protected Data $dataHelper,
    protected TokenizerInterface $tokenizer,
    protected TextChunkerInterface $textChunker,
    protected QueueInterface $queue,
    protected AiTranslatorWorker $queueWorker,
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
      $container->get('language_manager'),
      $container->get('tmgmt.data'),
      $container->get('ai.tokenizer'),
      $container->get('ai.text_chunker'),
      $container->get('queue')->get('ai_translator_worker', TRUE),
      $container->get('plugin.manager.queue_worker')->createInstance('ai_translator_worker'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function checkAvailable(TranslatorInterface $translator): AvailableResult {
    $settings = $translator->getSettings();
    $modelSelectionType = $settings['model_selection_type'] ?? NULL;
    $chatModel = $settings['chat_model'] ?? NULL;
    switch ($modelSelectionType) {
      case 'ai_translate':
        return AvailableResult::yes();

      case 'ai_tmgmt':
        if (\is_string($chatModel) && '' !== $chatModel) {
          return AvailableResult::yes();
        }
        break;
    }

    return AvailableResult::no($this->t('@translator is not available. Make sure it is properly <a href=:configured>configured</a>.', [
      '@translator' => $translator->label(),
      ':configured' => $translator->toUrl()->toString(),
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function requestTranslation(JobInterface $job): void {
    $this->requestJobItemsTranslation($job->getItems());
    if (!$job->isRejected()) {
      $job->submitted('The translation job has been submitted.');
    }
  }

  /**
   * Split HTML into smaller chunks.
   *
   * @param array|string $text
   *   The text.
   * @param int $maxChunkTokens
   *   The maximum number of tokens.
   *
   * @return array
   *   The chunks of html.
   */
  public function htmlSplitter(array|string $text, int $maxChunkTokens): array {
    $doc = new \DOMDocument();
    @$doc->loadHTML(\mb_convert_encoding($text, 'HTML-ENTITIES', 'UTF-8'));

    $currentSegment = "";
    $currentTokens = 0;
    $segments = [];

    foreach ($doc->getElementsByTagName('body')->item(0)->childNodes as $node) {
      $nodeHTML = $doc->saveHTML($node);
      $tokens = $this->tokenizer->countTokens($nodeHTML);

      if ($currentTokens + $tokens > $maxChunkTokens) {
        $segments[] = $currentSegment;
        $currentSegment = "";
        $currentTokens = 0;
      }

      $currentTokens += $tokens;
      $currentSegment .= $nodeHTML;
    }

    if (!empty($currentSegment)) {
      $segments[] = $currentSegment;
    }
    return $segments;
  }

  /**
   * Get all text nodes.
   *
   * @param mixed $node
   *   The DOM Node.
   *
   * @return array
   *   The text nodes.
   */
  protected function getAllTextNodes(mixed $node): array {
    $textNodes = [];
    if ($node->nodeType == \XML_TEXT_NODE) {
      $textNodes[] = $node;
    }
    elseif ($node->nodeType == \XML_ELEMENT_NODE) {
      foreach ($node->childNodes as $child) {
        $textNodes = \array_merge($textNodes, $this->getAllTextNodes($child));
      }
    }
    return $textNodes;
  }

  /**
   * Check if a string contains HTML.
   *
   * @param string $string
   *   The string to check.
   *
   * @return bool
   *   If the given string contains HTML.
   */
  protected function isHtml(string $string): bool {
    return \preg_match("/<[^<]+>/", $string, $m) != 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedRemoteLanguages(TranslatorInterface $translator): array {
    $languages = [];
    $site_languages = $this->languageManager->getLanguages();
    foreach ($site_languages as $langcode => $language) {
      $languages[$langcode] = $language->getName();
    }
    return $languages;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedTargetLanguages(TranslatorInterface $translator, $source_language): array {
    $languages = $this->getSupportedRemoteLanguages($translator);
    // There are no language pairs, any supported language can be translated
    // into the others. If the source language is part of the languages,
    // then return them all, just remove the source language.
    if (\array_key_exists($source_language, $languages)) {
      unset($languages[$source_language]);
      return $languages;
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function hasCheckoutSettings(JobInterface $job): bool {
    return FALSE;
  }

  /**
   * Local method to do request to AI service.
   *
   * @param \Drupal\tmgmt\TranslatorInterface $translator
   *   The translator entity to get the settings from.
   * @param string $action
   *   Action to be performed [translate, languages, detect].
   * @param array $request_query
   *   (Optional) Additional query params to be passed into the request.
   * @param array $options
   *   (Optional) Additional options that will be passed into the HTTP Request.
   *
   * @return string
   *   Translated string.
   */
  protected static function doRequest(
    TranslatorInterface $translator,
    string $action,
    array $request_query = [],
    array $options = [],
  ): string {
    if (!\in_array($action, ['translate', 'languages'], TRUE)) {
      throw new TMGMTException('Invalid action requested: @action', ['@action' => $action]);
    }

    $chunk = $request_query['text'];
    $settings = $translator->getSettings();
    $model_selection_type = $settings['model_selection_type'] ?? 'ai_tmgmt';
    switch ($model_selection_type) {
      case 'ai_translate':
        // Use AI Translate module's per-language prompts only when the module
        // exists and is configured.
        if (!\Drupal::moduleHandler()->moduleExists('ai_translate')) {
          throw new TMGMTException('AI Translate module is not available. Please enable the ai_translate module.');
        }

        $ai_translate_config = \Drupal::configFactory()->get('ai_translate.settings');
        if (empty($ai_translate_config->get('prompt'))) {
          throw new TMGMTException('AI Translate module is not configured. Please configure translation prompts at /admin/config/ai/translate.');
        }

        $entityStorage = \Drupal::entityTypeManager()->getStorage($request_query['item_type']);
        if (!($entityStorage instanceof ContentEntityStorageInterface)) {
          throw new TMGMTException('AI Translate module can only handle content entities.');
        }

        $entity = $entityStorage->load($request_query['item_id']);
        $text_metadata = \Drupal::service('ai_translate.text_extractor')->extractTextMetadata($entity);
        [$field_name] = explode(']', $request_query['key']);
        $field_metadata = [];
        foreach ($text_metadata as $metadata) {
          if ($metadata['field_name'] === $field_name) {
            $field_metadata = $metadata;
            break;
          }
        }

        // Translatable fields all have meta data.
        if (empty($field_metadata)) {
          return $chunk;
        }

        $lang_to = \Drupal::languageManager()->getLanguage($request_query['target']);
        $lang_from = $request_query['source'] ? \Drupal::languageManager()->getLanguage($request_query['source']) : NULL;
        $translationContext = [
          'entity_type_id' => $entity?->getEntityTypeId(),
          'bundle' => $entity?->bundle(),
          'entity_id' => $entity?->id(),
          'lang_to' => $lang_to->getId(),
          'field_name' => $field_name,
          'field_type' => $field_metadata['field_type'],
        ];

        try {
          return \Drupal::service('ai_translate.text_translator')
            ->translateContent(
              $chunk,
              $lang_to,
              $lang_from,
              $translationContext
            );
        }
        catch (TranslationException $e) {
          throw new TMGMTException($e->getMessage());
        }

      case 'ai_tmgmt':
      default:
        // Use basic chat-based translation with a custom prompt. This option
        // works without the AI Translate module but does not have per-language
        // prompts or potentially more complex context-specific prompts.
        $site_languages = \Drupal::languageManager()->getLanguages();
        $prompt = $settings['advanced']['prompt'] ?? 'Translate from %source% into %target% language';

        // Replace the source and target language in the prompt.
        $system_prompt = str_replace(
          ['%source%', '%target%'],
          [
            $site_languages[$request_query['source']]->getName(),
            $site_languages[$request_query['target']]->getName(),
          ],
          $prompt,
        );

        /** @var \Drupal\ai\AiProviderPluginManager $provider_manager */
        $provider_manager = \Drupal::service('ai.provider');
        /** @var \Drupal\ai\OperationType\Chat\ChatInterface $provider */
        $provider = $provider_manager->loadProviderFromSimpleOption($settings['chat_model']);
        $model_id = $provider_manager->getModelNameFromSimpleOption($settings['chat_model']);
        $messages = new ChatInput([
          new chatMessage('system', $system_prompt),
          new chatMessage('user', $chunk),
        ]);
        return $provider->chat($messages, $model_id)->getNormalized()->getText();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function requestJobItemsTranslation(array $job_items) {
    /** @var \Drupal\tmgmt\Entity\Job $job */
    $job = \reset($job_items)->getJob();
    $settings = $job->getTranslator()->getSettings();
    $this->tokenizer->setModel($settings['tokenizer_model']);
    $this->textChunker->setModel($settings['tokenizer_model']);
    $maxChunkTokens = (int) $settings['advanced']['max_tokens'] ?? 1024;
    $has_queued_items = FALSE;
    foreach ($job_items as $job_item) {
      if ($job->isContinuous()) {
        $job_item->active();
      }
      // Pull the source data array through the job and flatten it.
      $data = $this->dataHelper->filterTranslatable($job_item->getData());

      $fields = [];
      $keys_sequence = [];
      $translation = [];

      // Build AI query param and preserve initial array keys.
      foreach ($data as $key => $value) {
        // Split the long text into chunks.
        if ($this->isHtml($value['#text'])) {
          $chunks = $this->htmlSplitter($value['#text'], $maxChunkTokens);
        }
        else {
          $chunks = $this->textChunker->chunkText($value['#text'], $maxChunkTokens, 0);
        }
        $fields[$key] = $chunks;
        $keys_sequence[] = $key;
      }

      // Queue items to be processed.
      foreach ($fields as $key => $chunks) {
        foreach ($chunks as $chunk) {
          if (\trim($chunk) == '') {
            continue;
          }

          $item = [
            'job_item_id' => $job_item->id(),
            'translation' => $translation,
            'key' => $key,
            'keys_sequence' => $keys_sequence,
            'chunk' => $chunk,
          ];
          $this->queue->createItem($item);
          $has_queued_items = TRUE;
        }
      }
    }

    // Process the queue if we are not in CLI mode. CLI mode queue should be
    // processed via cron.
    if ($has_queued_items && !$job->isContinuous()) {

      // Now initiate processing the queue. In this manner, if there are any
      // failures, or the user abandons the batch UI, the cron queue and pick
      // up jobs once they are released.
      $this->processQueue();
    }
  }

  /**
   * Process the queue in batches.
   */
  protected function processQueue(): void {
    $batch_builder = new BatchBuilder();
    $batch_builder->setTitle($this->t('Translating job items'));
    $batch_builder->setFinishCallback([static::class, 'batchFinished']);
    $request_callable = [static::class, 'batchRequestTranslation'];

    // Claim the items we just added to the queue and process them in batch.
    while ($item = $this->queue->claimItem()) {
      $data = array_values($item->data);
      $data[] = $item;
      $batch_builder->addOperation($request_callable, $data);
    }

    // Start the batch.
    batch_set($batch_builder->toArray());
  }

  /**
   * Batch 'operation' callback for requesting translation.
   *
   * We pass more parameters than needed in case other implementations want to
   * use that context. The queue worker provided by this module, for example,
   * needs more context.
   *
   * @param string $job_item_id
   *   The tmgmt job item ID.
   * @param array $translation
   *   The translations array.
   * @param string $key
   *   The data key.
   * @param array $keys_sequence
   *   Array of field name keys.
   * @param string $chunk
   *   The text to be translated.
   * @param object|null $queue_item
   *   The queue item being processed if not called via the Queue Worker.
   * @param array $context
   *   The sandbox context.
   */
  public static function batchRequestTranslation(
    string $job_item_id,
    array $translation,
    string $key,
    array $keys_sequence,
    string $chunk,
    object|null $queue_item,
    array &$context,
  ): void {
    $jobItem = JobItem::load($job_item_id);
    try {
      if (!$jobItem) {
        throw new TMGMTException('Unable to load job item: @id', ['@id' => $job_item_id]);
      }
      // If the source entity has been deleted, a TMGMTException will be thrown.
      $jobItem->getSourceData();
    }
    catch (TMGMTException $exception) {
      \Drupal::logger('ai_tmgmt')->error(t('Unable to translate job item: @id, the following exception was thrown: @message', [
        '@id' => $job_item_id,
        '@message' => $exception->getMessage(),
      ]));
      if ($jobItem) {
        $jobItem->delete();
      }
      $context['results'] = [];
      return;
    }

    $job = $jobItem->getJob();
    $translator = $job->getTranslator();

    // Prepare the query parameters. An example key is as follows:
    // `title][0][value`.
    $query_params = [
      'source' => $job->getSourceLangcode(),
      'target' => $job->getTargetLangcode(),
      'text' => $chunk,
      'key' => $key,
      'item_type' => $jobItem->getItemType(),
      'item_id' => $jobItem->getItemId(),
    ];
    $result = static::doRequest($translator, 'translate', $query_params);
    if (!isset($context['results'][$job_item_id]['translation'])) {
      $context['results'][$job_item_id]['translation'] = [];
    }

    if (isset($context['results'][$job_item_id]['translation'][$key]) && $context['results'][$job_item_id]['translation'][$key]['#text'] !== NULL) {
      $context['results'][$job_item_id]['translation'][$key]['#text'] .= "\n" . $result;
    }
    else {
      $context['results'][$job_item_id]['translation'][$key]['#text'] = $result;
    }

    // Do partial saves: even though this is less performant, if the AI fails
    // on a particular field, better not to lose what it has already done.
    $tmgmtData = \Drupal::service('tmgmt.data');
    $data = $tmgmtData->unflatten($context['results'][$job_item_id]['translation']);
    $jobItem->addTranslatedData($data);

    // If we have a queue item passed (i.e., this is run via batch instead of
    // queue), then delete the queue item on completion. Queue Items always
    // have at least the 'item_id' property.
    if ($queue_item && isset($queue_item->item_id) && $queue_item->item_id) {
      /** @var \Drupal\Core\Queue\QueueInterface $queue */
      $queue = \Drupal::service('queue')->get('ai_translator_worker', TRUE);
      $queue->deleteItem($queue_item);
    }
  }

  /**
   * Batch 'operation' callback.
   *
   * @param bool $success
   *   Batch success.
   * @param array $results
   *   Results.
   * @param array $operations
   *   Operations.
   */
  public static function batchFinished(bool $success, array $results, array $operations): void {
    $tmgmtData = \Drupal::service('tmgmt.data');
    $jobs = [];
    foreach ($results as $job_item_id => $result) {
      $job_item = JobItem::load($job_item_id);
      if (!$job_item instanceof JobItemInterface) {
        continue;
      }

      $data = $tmgmtData->unflatten($result['translation']);
      $job_item->addTranslatedData($data);
      $job = $job_item->getJob();
      if ($job instanceof JobInterface && !isset($jobs[$job->id()])) {
        $jobs[$job->id()] = $job;
      }
    }

    // Output messages if via UI, or log messages in the logger if via cron.
    foreach ($jobs as $job) {
      if (PHP_SAPI === 'cli') {
        foreach ($job->getMessagesSince() as $message) {
          $type = $message->getType();
          // Ignore debug messages.
          if ($type === 'debug') {
            continue;
          }
          if ($type === 'status') {
            $type = 'info';
          }
          if ($text = $message->getMessage()) {
            \Drupal::logger('ai_tmgmt')->log($type, $text);
          }
        }
      }
      else {
        \tmgmt_write_request_messages($job);
      }
    }
  }

}
