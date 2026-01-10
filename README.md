# MCP Tool Gateway

[![CI](https://github.com/code-wheel/mcp-tool-gateway/actions/workflows/ci.yml/badge.svg)](https://github.com/code-wheel/mcp-tool-gateway/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/code-wheel/mcp-tool-gateway/graph/badge.svg)](https://codecov.io/gh/code-wheel/mcp-tool-gateway)
[![Latest Stable Version](https://poser.pugx.org/code-wheel/mcp-tool-gateway/v)](https://packagist.org/packages/code-wheel/mcp-tool-gateway)
[![License](https://poser.pugx.org/code-wheel/mcp-tool-gateway/license)](https://packagist.org/packages/code-wheel/mcp-tool-gateway)

A production-ready framework for PHP MCP (Model Context Protocol) servers. Features middleware pipeline, input validation, tool composition, caching, and event dispatching.

## Installation

```bash
composer require code-wheel/mcp-tool-gateway
```

## Features

- **Middleware Pipeline** - Chain validation, auth, logging, and custom middleware
- **Input Validation** - Reject malformed LLM inputs before execution
- **Tool Composition** - Combine multiple tool providers with namespacing
- **Caching** - PSR-16 cache support for discovery and read-only results
- **Events** - PSR-14 event dispatching for tool lifecycle
- **Framework Agnostic** - Works with Drupal, Laravel, Symfony, or vanilla PHP

## Quick Start

### Basic Usage

```php
use CodeWheel\McpToolGateway\ArrayToolProvider;
use CodeWheel\McpToolGateway\ToolInfo;
use CodeWheel\McpToolGateway\ToolResult;

// Create a tool provider
$provider = new ArrayToolProvider([
    'greet' => new ToolInfo(
        name: 'greet',
        label: 'Greet User',
        description: 'Says hello to a user',
        inputSchema: [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
            ],
            'required' => ['name'],
        ],
    ),
]);

// Register handler
$provider->setHandler('greet', function (array $args): ToolResult {
    return ToolResult::success("Hello, {$args['name']}!");
});

// Execute
$result = $provider->execute('greet', ['name' => 'World']);
echo $result->message; // "Hello, World!"
```

### Middleware Pipeline

```php
use CodeWheel\McpToolGateway\Middleware\MiddlewarePipeline;
use CodeWheel\McpToolGateway\Middleware\ValidatingMiddleware;
use CodeWheel\McpToolGateway\Middleware\LoggingMiddleware;
use CodeWheel\McpSchemaBuilder\SchemaValidator;

$pipeline = new MiddlewarePipeline($provider);

// Add validation (rejects malformed inputs)
$validator = new SchemaValidator();
$pipeline->add(new ValidatingMiddleware($provider, $validator));

// Add logging (PSR-3)
$pipeline->add(new LoggingMiddleware($logger));

// Execute through pipeline
$result = $pipeline->execute('create_user', [
    'email' => 'invalid',  // Will be rejected by validation
    'name' => 'John',
]);
```

### Composing Multiple Providers

```php
use CodeWheel\McpToolGateway\CompositeToolProvider;

// Combine providers with prefixes
$composite = new CompositeToolProvider([
    'drupal' => $drupalProvider,   // drupal/get_users, drupal/create_node
    'custom' => $customProvider,    // custom/my_tool
    'api' => $externalProvider,     // api/fetch_data
]);

// Or without prefixes (tools must have unique names)
$composite = new CompositeToolProvider([
    $provider1,
    $provider2,
], prefixed: false);

$allTools = $composite->getTools();
$result = $composite->execute('drupal/get_users', ['limit' => 10]);
```

### Caching

```php
use CodeWheel\McpToolGateway\CachingToolProvider;

$cached = new CachingToolProvider(
    $provider,
    $cache,              // PSR-16 CacheInterface
    discoveryTtl: 3600,  // Cache tool list for 1 hour
    resultTtl: 300,      // Cache read-only tool results for 5 minutes
    cacheableTools: ['get_config', 'list_users'],  // Tools to cache results
);

// First call hits provider, subsequent calls use cache
$tools = $cached->getTools();
```

### Events

```php
use CodeWheel\McpToolGateway\Middleware\EventMiddleware;
use CodeWheel\McpToolGateway\Event\ToolExecutionStarted;
use CodeWheel\McpToolGateway\Event\ToolExecutionSucceeded;
use CodeWheel\McpToolGateway\Event\ToolExecutionFailed;

// Add event dispatching (PSR-14)
$pipeline->add(new EventMiddleware($eventDispatcher));

// Listen for events
$dispatcher->addListener(ToolExecutionStarted::class, function ($event) {
    $this->metrics->increment("tool.{$event->toolName}.started");
});

$dispatcher->addListener(ToolExecutionSucceeded::class, function ($event) {
    $this->metrics->timing("tool.{$event->toolName}.duration", $event->duration);
});

$dispatcher->addListener(ToolExecutionFailed::class, function ($event) {
    $this->alerting->notify("Tool {$event->toolName} failed: {$event->exception->getMessage()}");
});
```

## Middleware

### Built-in Middleware

| Middleware | Purpose | Requires |
|------------|---------|----------|
| `ValidatingMiddleware` | Validates inputs against JSON Schema | `mcp-schema-builder` |
| `LoggingMiddleware` | Logs execution with timing | PSR-3 Logger |
| `EventMiddleware` | Dispatches lifecycle events | PSR-14 EventDispatcher |

### Custom Middleware

```php
use CodeWheel\McpToolGateway\Middleware\MiddlewareInterface;
use CodeWheel\McpToolGateway\ExecutionContext;
use CodeWheel\McpToolGateway\ToolResult;

class AuthorizationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AccessChecker $access,
    ) {}

    public function process(
        string $toolName,
        array $arguments,
        ExecutionContext $context,
        callable $next,
    ): ToolResult {
        // Check access before execution
        if (!$this->access->canExecute($context->userId, $toolName)) {
            return ToolResult::error("Access denied to tool: {$toolName}");
        }

        return $next($toolName, $arguments, $context);
    }
}

class RateLimitMiddleware implements MiddlewareInterface
{
    public function process(
        string $toolName,
        array $arguments,
        ExecutionContext $context,
        callable $next,
    ): ToolResult {
        $key = "{$context->userId}:{$toolName}";

        if ($this->limiter->isLimited($key)) {
            return ToolResult::error("Rate limit exceeded for {$toolName}");
        }

        $this->limiter->hit($key);
        return $next($toolName, $arguments, $context);
    }
}

class AuditMiddleware implements MiddlewareInterface
{
    public function process(
        string $toolName,
        array $arguments,
        ExecutionContext $context,
        callable $next,
    ): ToolResult {
        $start = microtime(true);

        try {
            $result = $next($toolName, $arguments, $context);

            $this->audit->log([
                'tool' => $toolName,
                'user' => $context->userId,
                'success' => $result->success,
                'duration' => microtime(true) - $start,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->audit->logError($toolName, $e);
            throw $e;
        }
    }
}
```

### Production Pipeline Example

```php
$pipeline = new MiddlewarePipeline($provider);

// Order matters: outer middleware wraps inner
$pipeline->add(new AuditMiddleware($auditor));              // 1. Audit everything
$pipeline->add(new RateLimitMiddleware($limiter));          // 2. Rate limiting
$pipeline->add(new AuthorizationMiddleware($access));       // 3. Access control
$pipeline->add(new ValidatingMiddleware($provider, $validator)); // 4. Input validation
$pipeline->add(new LoggingMiddleware($logger));             // 5. Execution logging
$pipeline->add(new EventMiddleware($dispatcher));           // 6. Lifecycle events

$result = $pipeline->execute('delete_user', ['user_id' => 123], $context);
```

## Execution Context

```php
use CodeWheel\McpToolGateway\ExecutionContext;

$context = ExecutionContext::create(
    userId: 'user-123',
    requestId: 'req-abc',
    scopes: ['read', 'write', 'admin'],
    metadata: ['client' => 'claude-desktop'],
);

// Check scopes
$context->hasScope('write');           // true
$context->hasAnyScope(['admin', 'superuser']); // true

// Use in middleware
if (!$context->hasScope('write')) {
    return ToolResult::error('Write scope required');
}
```

## Custom Tool Provider

```php
use CodeWheel\McpToolGateway\ToolProviderInterface;
use CodeWheel\McpToolGateway\ToolInfo;
use CodeWheel\McpToolGateway\ToolResult;
use CodeWheel\McpToolGateway\ExecutionContext;

class DrupalToolProvider implements ToolProviderInterface
{
    public function __construct(
        private readonly ToolPluginManager $pluginManager,
    ) {}

    public function getTools(): array
    {
        $tools = [];
        foreach ($this->pluginManager->getDefinitions() as $id => $def) {
            $tools[$id] = new ToolInfo(
                name: $id,
                label: $def['label'],
                description: $def['description'],
                inputSchema: $def['input_schema'],
                annotations: $def['annotations'] ?? [],
                provider: $def['provider'],
            );
        }
        return $tools;
    }

    public function getTool(string $toolName): ?ToolInfo
    {
        $tools = $this->getTools();
        return $tools[$toolName] ?? null;
    }

    public function execute(
        string $toolName,
        array $arguments,
        ?ExecutionContext $context = null,
    ): ToolResult {
        $plugin = $this->pluginManager->createInstance($toolName);
        $result = $plugin->execute($arguments);

        return new ToolResult(
            success: $result['success'],
            message: $result['message'],
            data: $result['data'] ?? [],
        );
    }
}
```

## Gateway Pattern

For MCP servers with many tools, use the gateway pattern to reduce context window usage:

```php
use CodeWheel\McpToolGateway\ToolGateway;

$gateway = new ToolGateway($provider);

// Register just 3 gateway tools instead of 100+ individual tools
foreach ($gateway->getGatewayTools() as $tool) {
    $mcpServer->registerTool($tool['name'], $tool['handler'], $tool['inputSchema']);
}

// LLM uses:
// - gateway/discover-tools { "query": "user" }
// - gateway/get-tool-info { "tool_name": "create-user" }
// - gateway/execute-tool { "tool_name": "create-user", "arguments": {...} }
```

## Integration with mcp-schema-builder

```php
use CodeWheel\McpSchemaBuilder\SchemaBuilder;
use CodeWheel\McpSchemaBuilder\McpSchema;
use CodeWheel\McpSchemaBuilder\SchemaValidator;

// Build schema with presets
$schema = SchemaBuilder::object()
    ->property('user_id', McpSchema::entityId('user')->required())
    ->property('status', SchemaBuilder::string()->enum(['active', 'blocked']))
    ->merge(McpSchema::pagination())
    ->build();

// Validate in middleware
$validator = new SchemaValidator();
$pipeline->add(new ValidatingMiddleware($provider, $validator));
```

## Integration with mcp-error-codes

```php
use CodeWheel\McpErrorCodes\McpError;

// In your tool handler
public function execute(array $args): ToolResult
{
    $user = $this->users->find($args['user_id']);

    if (!$user) {
        return McpError::notFound('user', $args['user_id'])
            ->withSuggestion('Check if the user ID is correct')
            ->toToolResult();
    }

    // ...
}
```

## License

MIT License - see [LICENSE](LICENSE) file.

