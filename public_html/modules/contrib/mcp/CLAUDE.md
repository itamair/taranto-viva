# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## MCP Drupal Module Development Guidelines

## Commands
- **Install Dependencies**: `composer install`
- **Run All Tests**: `./vendor/bin/phpunit --testsuite Functional`
- **Run Single Test**: `vendor/bin/phpunit path/to/specific/test.php`
- **Code Style Check**: `vendor/bin/grumphp run` or `vendor/bin/phpcs`
- **Enable Module**: `drush en mcp`
- **Generate MCP Plugin**: `drush generate mcp:plugin`

## Architecture

This module implements the Model Context Protocol (MCP) for Drupal, allowing Drupal sites to act as MCP servers. The key architectural components:

### Plugin System
- **MCP Plugins** (`src/Plugin/Mcp/`): Extend `McpPluginBase` and implement `McpInterface`
- **JSON-RPC Methods** (`src/Plugin/McpJsonRpc/`): Handle MCP protocol methods (Initialize, ToolsList, ResourcesList, etc.)
- **Plugin Discovery**: Uses PHP attributes (`#[Mcp]`) for plugin annotation
- **Plugin Manager**: `McpPluginManager` handles plugin instantiation with configuration from `mcp.settings`

### Core Interfaces
- `McpInterface`: Main plugin interface defining tools, resources, and execution methods
- `ResourceInterface` & `ToolInterface`: Define server features for MCP protocol
- `McpJsonRpcMethodBase`: Base class for JSON-RPC method implementations

### Tool Annotations
Tools can include optional annotations that provide hints to clients about tool behavior. All annotation properties are **advisory hints** and not guaranteed to reflect actual behavior.

**ToolAnnotations Properties:**
- `title`: Human-readable display name (precedence: annotations.title > tool.title > tool.name)
- `readOnlyHint`: Tool doesn't modify environment (default: false)
- `idempotentHint`: Repeated calls have no additional effect (default: false)
- `destructiveHint`: May perform destructive updates (default: true)
- `openWorldHint`: May interact with external entities (default: true)

**Example Usage:**
```php
use Drupal\mcp\ServerFeatures\Tool;
use Drupal\mcp\ServerFeatures\ToolAnnotations;

public function getTools(): array {
  return [
    new Tool(
      name: "read_content",
      description: "Read content from the site",
      inputSchema: [...],
      annotations: new ToolAnnotations(
        title: "Read Content",
        readOnlyHint: true,
        idempotentHint: true,
        destructiveHint: false,
        openWorldHint: false,
      ),
    ),
  ];
}
```

**Note:** Annotations describe the inherent behavior of the tool implementation and should only be set by plugin developers. They are not user-configurable since they reflect how the tool actually behaves.

### Authentication
- Custom authentication provider: `McpAuthProvider`
- Access control via `hasAccess()` method on plugins
- Page cache policy: `DisallowMcpAuthRequests`

### Module Structure
- Main module: `mcp` - Core functionality including all plugins (AI agents, function calling, content, JSON API, Drush caller, Tool API)
- Optional sub-module:
  - `mcp_studio`: MCP Studio interface (optional development tool)

## Code Style
- Follow **Drupal Coding Standards** (enforced via PHPCS)
- Use **PSR-4** autoloading with `Drupal\mcp` namespace
- Add `declare(strict_types=1)` to all PHP files
- Use **type hints** and **return types** consistently
- **Class naming**: `CamelCase` with `Mcp` prefix (e.g., `McpPluginBase`)
- **Method naming**: `camelCase` (e.g., `getTools()`)
- **Interface naming**: Use `Interface` suffix (e.g., `McpInterface`)
- Organize code into **PSR-4 namespaces** by feature
- Use **PHPDoc** comment blocks for all classes/methods
- Follow **Drupal plugin patterns** for extensions
- Avoid debug functions (var_dump, print_r, etc.)