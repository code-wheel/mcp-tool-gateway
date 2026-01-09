# MCP Tool Gateway

[![CI](https://github.com/code-wheel/mcp-tool-gateway/actions/workflows/ci.yml/badge.svg)](https://github.com/code-wheel/mcp-tool-gateway/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/code-wheel/mcp-tool-gateway/graph/badge.svg)](https://codecov.io/gh/code-wheel/mcp-tool-gateway)
[![Latest Stable Version](https://poser.pugx.org/code-wheel/mcp-tool-gateway/v)](https://packagist.org/packages/code-wheel/mcp-tool-gateway)
[![License](https://poser.pugx.org/code-wheel/mcp-tool-gateway/license)](https://packagist.org/packages/code-wheel/mcp-tool-gateway)

A framework-agnostic tool gateway for the Model Context Protocol (MCP). Instead of registering hundreds of tools with an MCP client, register just 3 gateway tools that allow dynamic discovery and execution.

## The Problem

MCP servers with many tools face challenges:
- Large tool lists consume context window space
- Every tool must be registered upfront
- Adding new tools requires client reconnection

## The Solution

The Tool Gateway pattern exposes 3 meta-tools:

| Tool | Purpose |
|------|---------|
| `gateway/discover-tools` | List available tools with filtering |
| `gateway/get-tool-info` | Get schema for a specific tool |
| `gateway/execute-tool` | Execute any tool dynamically |

This reduces context usage while maintaining full capability.

## Installation

```bash
composer require code-wheel/mcp-tool-gateway
```

## Quick Start

```php
use CodeWheel\McpToolGateway\ArrayToolProvider;
use CodeWheel\McpToolGateway\ToolGateway;
use CodeWheel\McpToolGateway\ToolInfo;
use CodeWheel\McpToolGateway\ToolResult;

// 1. Create a tool provider and register your tools
$provider = new ArrayToolProvider();

$provider->register(
    new ToolInfo(
        name: 'greet',
        label: 'Greet User',
        description: 'Says hello to a user',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'Name to greet'],
            ],
            'required' => ['name'],
        ],
        annotations: ['readOnlyHint' => true],
    ),
    fn(array $args) => ToolResult::success("Hello, {$args['name']}!")
);

$provider->register(
    new ToolInfo(
        name: 'add',
        label: 'Add Numbers',
        description: 'Adds two numbers together',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'a' => ['type' => 'number'],
                'b' => ['type' => 'number'],
            ],
            'required' => ['a', 'b'],
        ],
    ),
    fn(array $args) => ToolResult::success(
        "Result: " . ($args['a'] + $args['b']),
        ['result' => $args['a'] + $args['b']]
    )
);

// 2. Create the gateway
$gateway = new ToolGateway($provider);

// 3. Register gateway tools with your MCP server
foreach ($gateway->getGatewayTools() as $tool) {
    $server->registerTool(
        $tool['name'],
        $tool['handler'],
        $tool['inputSchema'],
        $tool['description'],
    );
}
```

## Custom Tool Provider

Implement `ToolProviderInterface` to integrate with your framework:

```php
use CodeWheel\McpToolGateway\ToolProviderInterface;
use CodeWheel\McpToolGateway\ToolInfo;
use CodeWheel\McpToolGateway\ToolResult;
use CodeWheel\McpToolGateway\ExecutionContext;

class MyFrameworkToolProvider implements ToolProviderInterface
{
    public function __construct(
        private readonly MyToolRegistry $registry,
    ) {}

    public function getTools(): array
    {
        $tools = [];
        foreach ($this->registry->all() as $tool) {
            $tools[$tool->id] = new ToolInfo(
                name: $tool->id,
                label: $tool->label,
                description: $tool->description,
                inputSchema: $tool->schema,
                provider: $tool->module,
            );
        }
        return $tools;
    }

    public function getTool(string $toolName): ?ToolInfo
    {
        $tool = $this->registry->get($toolName);
        return $tool ? new ToolInfo(...) : null;
    }

    public function execute(string $toolName, array $arguments, ?ExecutionContext $context = null): ToolResult
    {
        $result = $this->registry->execute($toolName, $arguments);
        return ToolResult::success($result['message'], $result['data']);
    }
}
```

## How It Works

### 1. Discovery

```
LLM: "What tools are available for user management?"

→ gateway/discover-tools { "query": "user" }

← {
    "success": true,
    "count": 3,
    "tools": [
        {"name": "create-user", "label": "Create User", "description": "..."},
        {"name": "update-user", "label": "Update User", "description": "..."},
        {"name": "delete-user", "label": "Delete User", "description": "..."}
    ]
}
```

### 2. Get Schema

```
LLM: "How do I create a user?"

→ gateway/get-tool-info { "tool_name": "create-user" }

← {
    "success": true,
    "name": "create-user",
    "input_schema": {
        "type": "object",
        "properties": {
            "email": {"type": "string", "format": "email"},
            "name": {"type": "string"},
            "role": {"type": "string", "enum": ["admin", "user"]}
        },
        "required": ["email", "name"]
    }
}
```

### 3. Execute

```
LLM: "Create a user named John"

→ gateway/execute-tool {
    "tool_name": "create-user",
    "arguments": {"email": "john@example.com", "name": "John"}
}

← {
    "success": true,
    "message": "User created successfully",
    "user_id": 123
}
```

## Tool Prefix

Add a prefix to gateway tool names:

```php
$gateway = new ToolGateway($provider, toolPrefix: 'myapp');
// Tools: myapp/gateway/discover-tools, myapp/gateway/get-tool-info, etc.
```

## License

MIT License - see [LICENSE](LICENSE) file.
