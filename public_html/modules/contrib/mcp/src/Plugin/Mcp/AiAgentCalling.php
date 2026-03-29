<?php

namespace Drupal\mcp\Plugin\Mcp;

use Drupal\ai_agents\PluginInterfaces\AiAgentInterface;
use Drupal\ai_agents\Task\Task;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp\Attribute\Mcp;
use Drupal\mcp\Plugin\McpPluginBase;
use Drupal\mcp\ServerFeatures\Tool;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the mcp.
 */
#[Mcp(
  id: 'aia',
  name: new TranslatableMarkup('AI Agent Calling'),
  description: new TranslatableMarkup(
    'Provides ai agent calling functionality.'
  ),
)]
class AiAgentCalling extends McpPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The pluginManagerAiAgents service.
   *
   * @var \Drupal\ai_agents\PluginManager\AiAgentManager
   */
  protected $agentManager;

  /**
   * The pluginManagerAiProvider service.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $providerPlugin;

  /**
   * {@inheritDoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    $instance = parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition,
    );

    $instance->agentManager = $container->get(
      'plugin.manager.ai_agents',
      ContainerInterface::NULL_ON_INVALID_REFERENCE
    );
    $instance->providerPlugin = $container->get(
      'ai.provider',
      ContainerInterface::NULL_ON_INVALID_REFERENCE
    );

    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function checkRequirements(): bool {
    return $this->agentManager !== NULL && $this->providerPlugin !== NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function getRequirementsDescription(): string {
    if (!$this->checkRequirements()) {
      $missing = [];
      if ($this->agentManager === NULL) {
        $missing[] = $this->t('AI Agents module');
      }
      if ($this->providerPlugin === NULL) {
        $missing[] = $this->t('AI module with provider configuration');
      }
      return $this->t('The following must be installed and configured: @missing', [
        '@missing' => implode(', ', $missing),
      ]);
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getTools(): array {
    $tools = [];

    foreach (
      $this->agentManager->getDefinitions() as $agent_id => $agent_definition
    ) {
      /** @var \Drupal\ai_agents\PluginInterfaces\AiAgentInterface $instance */
      $instance = $this->agentManager->createInstance($agent_id);

      if (!$this->checkAgentAccess($instance)) {
        continue;
      }

      if (!$instance->isAvailable()) {
        continue;
      }

      $capabilities = $instance->agentsCapabilities();
      foreach ($capabilities as $capability_name => $capability) {
        $tools[] = new Tool(
          name: "{$agent_id}__{$capability_name}",
          description: $capability['description'],
          inputSchema: [
            'type'       => 'object',
            'properties' => [
              'prompt' => [
                'type'        => 'string',
                'description' => 'The prompt to enable drupal modules or a question.',
              ],
            ],
            'required'   => ['prompt'],
          ],
        );
      }
    }

    return $tools;
  }

  /**
   * {@inheritdoc}
   */
  public function executeTool(string $toolId, mixed $arguments): array {
    $prompt = $arguments['prompt'];
    if (empty($prompt)) {
      throw new \InvalidArgumentException(
        'Prompt is required.'
      );
    }

    $agent_definitions = $this->agentManager->getDefinitions();

    $desired_agent = NULL;
    foreach ($agent_definitions as $agent_id => $agent_definition) {
      $agent = $this->agentManager->createInstance($agent_id);

      if (!$this->checkAgentAccess($agent) || !$agent->isAvailable()) {
        continue;
      }

      $capabilities = $agent->agentsCapabilities();
      foreach ($capabilities as $capability_name => $capability) {
        $toolName = "{$agent_id}__{$capability_name}";
        $sanitizedName = $this->sanitizeToolName($toolName);
        $oldToolName = "$agent_id:$capability_name";
        if ($sanitizedName === $toolId ||
            md5($oldToolName) === $toolId ||
            md5($toolName) === $toolId) {
          $desired_agent = $agent;
          break;
        }
      }
    }

    if (!$desired_agent) {
      throw new \InvalidArgumentException(
        'Agent or Capability not found.'
      );
    }

    $defaults = $this->providerPlugin->getDefaultProviderForOperationType(
      'chat_with_complex_json'
    );

    if (!$defaults) {
      throw new \InvalidArgumentException(
        'No default provider found for operation type.'
      );
    }
    $task = new Task($prompt);
    $task->setComments($this->messages ?? []);
    $desired_agent->setTask($task);
    $desired_agent->setAiProvider(
      $this->providerPlugin->createInstance($defaults['provider_id'])
    );
    $desired_agent->setModelName($defaults['model_id']);
    $desired_agent->setAiConfiguration([]);
    $desired_agent->setCreateDirectly(TRUE);

    $solvability = $desired_agent->determineSolvability();
    switch ($solvability) {
      case AiAgentInterface::JOB_NEEDS_ANSWERS:
        $questions = $desired_agent->askQuestion();

        return [
          [
            "type" => "text",
            "text" => implode("\n", $questions),
          ],
        ];

      case AiAgentInterface::JOB_NOT_SOLVABLE:
        return [
          [
            "type" => "text",
            "text" => 'Task is not solvable by this agent. Please rephrase.',
          ],
        ];

      case AiAgentInterface::JOB_SHOULD_ANSWER_QUESTION:
        return [
          [
            "type" => "text",
            "text" => $desired_agent->answerQuestion(),
          ],
        ];

      case AiAgentInterface::JOB_INFORMS:
        return [
          [
            "type" => "text",
            "text" => $desired_agent->inform(),
          ],
        ];

      case AiAgentInterface::JOB_SOLVABLE:
        $response = $desired_agent->solve();
        if ($response instanceof TranslatableMarkup) {
          try {
            $response = $response->render();
          }
          // For some cases the response can't render because of missing vars.
          // In this case we just return the json serialized response.
          catch (\Exception $e) {
            // Ideally this should not happen. But for some cases it does.
            // As there is missing arguments in the translatable markup.
            $response = $response->getUntranslatedString();
          }
        }

        return [
          [
            "type" => "text",
            "text" => $response,
          ],
        ];

      default:
        return [
          [
            "type" => "text",
            "text" => 'Unknown solvability status',
          ],
        ];
    }
  }

  /**
   * Check agent access handling both boolean and AccessResult return types.
   *
   * @param \Drupal\ai_agents\PluginInterfaces\AiAgentInterface $agent
   *   The agent instance.
   *
   * @return bool
   *   TRUE if access is allowed, FALSE otherwise.
   */
  protected function checkAgentAccess(AiAgentInterface $agent): bool {
    $access = $agent->hasAccess();

    // Handle boolean return type (for compatibility with some agents)
    if (is_bool($access)) {
      return $access;
    }

    // Handle AccessResult return type (standard)
    if (method_exists($access, 'isAllowed')) {
      return $access->isAllowed();
    }

    // Fallback: deny access if unexpected type.
    return FALSE;
  }

}
