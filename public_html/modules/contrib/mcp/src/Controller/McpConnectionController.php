<?php

declare(strict_types=1);

namespace Drupal\mcp\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller for displaying MCP connection information.
 */
class McpConnectionController extends ControllerBase {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * Constructs a new McpConnectionController object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('request_stack')
    );
  }

  /**
   * Displays MCP connection information.
   *
   * @return array
   *   A render array containing the connection information.
   */
  public function connectionInfo(): array {
    $request = $this->requestStack->getCurrentRequest();
    $base_url = $request->getSchemeAndHttpHost();
    $mcp_endpoint = $base_url . '/mcp/post';

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['mcp-connection-info']],
    ];

    $build['client_config_section'] = [
      '#type' => 'details',
      '#title' => $this->t('Client Configuration Examples'),
      '#open' => TRUE,
    ];

    $build['client_config_section']['claude_desktop'] = [
      '#type' => 'details',
      '#title' => $this->t('Claude Desktop'),
      '#open' => FALSE,
    ];

    $build['client_config_section']['claude_desktop']['location'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('<strong>Configuration file location:</strong>'),
    ];

    $build['client_config_section']['claude_desktop']['paths'] = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => [
        $this->t('<strong>macOS:</strong> <code>~/Library/Application Support/Claude/claude_desktop_config.json</code>'),
        $this->t('<strong>Windows:</strong> <code>%APPDATA%/Claude/claude_desktop_config.json</code>'),
      ],
    ];

    $build['client_config_section']['claude_desktop']['stdio_config_label'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('<strong>STDIO Transport Configuration (Recommended):</strong>'),
    ];

    $claude_desktop_stdio_config = json_encode([
      'mcpServers' => [
        'mcp-server-drupal' => [
          'command' => 'docker',
          'args' => [
            'run',
            '-i',
            '--rm',
            '-e',
            'DRUPAL_AUTH_USER',
            '-e',
            'DRUPAL_AUTH_PASSWORD',
            '--network=host',
            'ghcr.io/omedia/mcp-server-drupal:latest',
            '--drupal-url=' . $base_url,
            '--unsafe-net',
          ],
          'env' => [
            'DRUPAL_AUTH_USER' => 'your-drupal-username',
            'DRUPAL_AUTH_PASSWORD' => 'your-drupal-password',
          ],
        ],
      ],
      'globalShortcut' => '',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    $build['client_config_section']['claude_desktop']['stdio_config'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['code-container']],
      'pre' => [
        '#type' => 'html_tag',
        '#tag' => 'pre',
        '#value' => $claude_desktop_stdio_config,
        '#attributes' => ['class' => ['language-json'], 'id' => 'claude-desktop-stdio-config'],
      ],
      'button' => [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $this->t('Copy'),
        '#attributes' => [
          'class' => ['copy-button'],
          'onclick' => "navigator.clipboard.writeText(document.getElementById('claude-desktop-stdio-config').textContent); this.textContent = 'Copied!'; setTimeout(() => this.textContent = 'Copy', 2000);",
          'style' => 'position: absolute; top: 5px; right: 5px; padding: 5px 10px; background: #007cba; color: white; border: none; border-radius: 3px; cursor: pointer;',
        ],
      ],
    ];

    $build['client_config_section']['claude_desktop']['http_config_label'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('<strong>HTTP Transport Configuration (if supported):</strong>'),
    ];

    $claude_desktop_http_config = json_encode([
      'mcpServers' => [
        'drupal-mcp' => [
          'url' => $mcp_endpoint,
          'auth' => [
            'type' => 'basic',
            'username' => 'your-drupal-username',
            'password' => 'your-drupal-password',
          ],
        ],
      ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    $build['client_config_section']['claude_desktop']['http_config'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['code-container']],
      'pre' => [
        '#type' => 'html_tag',
        '#tag' => 'pre',
        '#value' => $claude_desktop_http_config,
        '#attributes' => ['class' => ['language-json'], 'id' => 'claude-desktop-http-config'],
      ],
      'button' => [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $this->t('Copy'),
        '#attributes' => [
          'class' => ['copy-button'],
          'onclick' => "navigator.clipboard.writeText(document.getElementById('claude-desktop-http-config').textContent); this.textContent = 'Copied!'; setTimeout(() => this.textContent = 'Copy', 2000);",
          'style' => 'position: absolute; top: 5px; right: 5px; padding: 5px 10px; background: #007cba; color: white; border: none; border-radius: 3px; cursor: pointer;',
        ],
      ],
    ];

    $build['client_config_section']['claude_desktop']['restart_note'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#attributes' => ['class' => ['description']],
      '#value' => $this->t('Note: Restart Claude Desktop after updating the configuration file.'),
    ];

    $build['client_config_section']['claude_code'] = [
      '#type' => 'details',
      '#title' => $this->t('Claude Code'),
      '#open' => FALSE,
    ];

    $build['client_config_section']['claude_code']['info'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Add the MCP server using the following terminal command:'),
    ];

    $claude_code_command = sprintf(
      'claude code add-mcp-server mcp-server-drupal docker run -i --rm -e DRUPAL_AUTH_USER -e DRUPAL_AUTH_PASSWORD --network=host ghcr.io/omedia/mcp-server-drupal:latest --drupal-url=%s --unsafe-net',
      $base_url
    );

    $build['client_config_section']['claude_code']['command'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['code-container']],
      'pre' => [
        '#type' => 'html_tag',
        '#tag' => 'pre',
        '#value' => $claude_code_command,
        '#attributes' => ['class' => ['language-bash'], 'id' => 'claude-code-command'],
      ],
      'button' => [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $this->t('Copy'),
        '#attributes' => [
          'class' => ['copy-button'],
          'onclick' => "navigator.clipboard.writeText(document.getElementById('claude-code-command').textContent); this.textContent = 'Copied!'; setTimeout(() => this.textContent = 'Copy', 2000);",
          'style' => 'position: absolute; top: 5px; right: 5px; padding: 5px 10px; background: #007cba; color: white; border: none; border-radius: 3px; cursor: pointer;',
        ],
      ],
    ];

    $build['client_config_section']['claude_code']['env_note'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#attributes' => ['class' => ['description']],
      '#value' => $this->t('Set the DRUPAL_AUTH_USER and DRUPAL_AUTH_PASSWORD environment variables before running the command.'),
    ];

    $build['client_config_section']['cursor'] = [
      '#type' => 'details',
      '#title' => $this->t('Cursor'),
      '#open' => FALSE,
    ];

    $build['client_config_section']['cursor']['stdio_config_label'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('<strong>STDIO Transport Configuration (Recommended):</strong>'),
    ];

    $cursor_stdio_config = json_encode([
      'mcpServers' => [
        'mcp-server-drupal' => [
          'command' => 'docker',
          'args' => [
            'run',
            '-i',
            '--rm',
            '-e',
            'DRUPAL_AUTH_USER',
            '-e',
            'DRUPAL_AUTH_PASSWORD',
            '--network=host',
            'ghcr.io/omedia/mcp-server-drupal:latest',
            '--drupal-url=' . $base_url,
            '--unsafe-net',
          ],
          'env' => [
            'DRUPAL_AUTH_USER' => 'your-drupal-username',
            'DRUPAL_AUTH_PASSWORD' => 'your-drupal-password',
          ],
        ],
      ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    $build['client_config_section']['cursor']['stdio_config'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['code-container']],
      'pre' => [
        '#type' => 'html_tag',
        '#tag' => 'pre',
        '#value' => $cursor_stdio_config,
        '#attributes' => ['class' => ['language-json'], 'id' => 'cursor-stdio-config'],
      ],
      'button' => [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $this->t('Copy'),
        '#attributes' => [
          'class' => ['copy-button'],
          'onclick' => "navigator.clipboard.writeText(document.getElementById('cursor-stdio-config').textContent); this.textContent = 'Copied!'; setTimeout(() => this.textContent = 'Copy', 2000);",
          'style' => 'position: absolute; top: 5px; right: 5px; padding: 5px 10px; background: #007cba; color: white; border: none; border-radius: 3px; cursor: pointer;',
        ],
      ],
    ];

    $build['client_config_section']['cursor']['http_config_label'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('<strong>HTTP Transport Configuration (if supported):</strong>'),
    ];

    $cursor_http_config = json_encode([
      'mcpServers' => [
        'drupal-mcp' => [
          'url' => $mcp_endpoint,
          'auth' => [
            'type' => 'basic',
            'username' => 'your-drupal-username',
            'password' => 'your-drupal-password',
          ],
        ],
      ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    $build['client_config_section']['cursor']['http_config'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['code-container']],
      'pre' => [
        '#type' => 'html_tag',
        '#tag' => 'pre',
        '#value' => $cursor_http_config,
        '#attributes' => ['class' => ['language-json'], 'id' => 'cursor-http-config'],
      ],
      'button' => [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $this->t('Copy'),
        '#attributes' => [
          'class' => ['copy-button'],
          'onclick' => "navigator.clipboard.writeText(document.getElementById('cursor-http-config').textContent); this.textContent = 'Copied!'; setTimeout(() => this.textContent = 'Copy', 2000);",
          'style' => 'position: absolute; top: 5px; right: 5px; padding: 5px 10px; background: #007cba; color: white; border: none; border-radius: 3px; cursor: pointer;',
        ],
      ],
    ];

    $build['client_config_section']['cursor']['restart_note'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#attributes' => ['class' => ['description']],
      '#value' => $this->t('Note: Restart Cursor after updating the configuration file.'),
    ];

    $build['transport_methods'] = [
      '#type' => 'details',
      '#title' => $this->t('Connection Methods'),
      '#open' => FALSE,
    ];

    $build['transport_methods']['description'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Drupal MCP supports two transport methods. Choose the one that best fits your client:'),
    ];

    $build['transport_methods']['http_transport'] = [
      '#type' => 'details',
      '#title' => $this->t('1. HTTP Transport (Direct Connection)'),
      '#open' => TRUE,
    ];

    $build['transport_methods']['http_transport']['endpoint'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['mcp-endpoint', 'code-container'], 'style' => 'margin: 10px 0;'],
      'label' => [
        '#type' => 'html_tag',
        '#tag' => 'strong',
        '#value' => $this->t('MCP Endpoint URL:'),
      ],
      'value' => [
        '#type' => 'html_tag',
        '#tag' => 'code',
        '#value' => $mcp_endpoint,
        '#prefix' => ' ',
        '#attributes' => ['id' => 'mcp-endpoint-url', 'style' => 'padding: 5px; background: #f4f4f4;'],
      ],
      'button' => [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $this->t('Copy'),
        '#attributes' => [
          'class' => ['copy-button'],
          'onclick' => "navigator.clipboard.writeText(document.getElementById('mcp-endpoint-url').textContent); this.textContent = 'Copied!'; setTimeout(() => this.textContent = 'Copy', 2000);",
          'style' => 'margin-left: 10px; padding: 5px 10px; background: #007cba; color: white; border: none; border-radius: 3px; cursor: pointer;',
        ],
      ],
    ];

    $build['transport_methods']['http_transport']['info'] = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => [
        $this->t('<strong>Transport:</strong> HTTP POST with JSON-RPC 2.0'),
        $this->t('<strong>Authentication:</strong> Basic Authentication'),
        $this->t('<strong>Best for:</strong> Remote Drupal sites, streamable HTTP clients'),
      ],
    ];

    $build['transport_methods']['stdio_transport'] = [
      '#type' => 'details',
      '#title' => $this->t('2. STDIO Transport (Recommended)'),
      '#open' => TRUE,
    ];

    $build['transport_methods']['stdio_transport']['info'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Uses a Docker container for secure communication. Recommended for Claude Desktop and Cursor.'),
    ];

    $build['transport_methods']['stdio_transport']['docker'] = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => [
        $this->t('<strong>Docker Image:</strong> <code>ghcr.io/omedia/mcp-server-drupal:latest</code>'),
        $this->t('<strong>Transport:</strong> STDIO with Docker'),
        $this->t('<strong>Best for:</strong> Claude Desktop, Claude Code, Cursor'),
      ],
    ];

    $build['protocol_info'] = [
      '#type' => 'details',
      '#title' => $this->t('Protocol Information'),
      '#open' => FALSE,
    ];

    $build['protocol_info']['content'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      'info' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('This server implements the Model Context Protocol (MCP) specification, enabling AI-powered interactions with your Drupal content and tools.'),
      ],
      'links' => [
        '#theme' => 'item_list',
        '#list_type' => 'ul',
        '#items' => [
          [
            '#markup' => $this->t('MCP Specification: <a href="@url" target="_blank">@url</a>', [
              '@url' => 'https://modelcontextprotocol.io',
            ]),
          ],
          [
            '#markup' => $this->t('Documentation: <a href="@url" target="_blank">@url</a>', [
              '@url' => 'https://drupalmcp.io',
            ]),
          ],
          [
            '#markup' => $this->t('STDIO Binary: <a href="@url" target="_blank">GitHub: omedia/mcp-server-drupal</a>', [
              '@url' => 'https://github.com/omedia/mcp-server-drupal',
            ]),
          ],
          [
            '#markup' => $this->t('Protocol Version: 2024-11-05'),
          ],
        ],
      ],
    ];

    $build['#attached']['library'][] = 'mcp/admin-styles';
    $build['#attached']['html_head'][] = [
      [
        '#type' => 'html_tag',
        '#tag' => 'style',
        '#value' => '.code-container { position: relative; } .code-container pre { padding-right: 80px; overflow-x: auto; background: #f4f4f4; padding: 15px; border-radius: 4px; } .copy-button:hover { opacity: 0.9; }',
      ],
      'mcp-connection-styles',
    ];

    return $build;
  }

}
