<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway;

/**
 * Simple in-memory tool provider for testing and simple use cases.
 *
 * Register tools with handlers:
 * ```php
 * $provider = new ArrayToolProvider();
 * $provider->register(
 *     new ToolInfo('greet', 'Greet', 'Says hello', ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]]),
 *     fn(array $args) => ToolResult::success("Hello, {$args['name']}!")
 * );
 * ```
 */
class ArrayToolProvider implements ToolProviderInterface
{
    /** @var array<string, ToolInfo> */
    private array $tools = [];

    /** @var array<string, callable> */
    private array $handlers = [];

    /**
     * @param array<string, ToolInfo> $tools Initial tools (handlers can be set later with setHandler).
     */
    public function __construct(array $tools = [])
    {
        foreach ($tools as $name => $tool) {
            $this->tools[$name] = $tool;
        }
    }

    /**
     * Sets a handler for a tool.
     *
     * @param string $toolName The tool name.
     * @param callable $handler Function that receives (array $arguments, ?ExecutionContext $context) and returns ToolResult.
     */
    public function setHandler(string $toolName, callable $handler): self
    {
        $this->handlers[$toolName] = $handler;
        return $this;
    }

    /**
     * Registers a tool with its handler.
     *
     * @param ToolInfo $tool The tool metadata.
     * @param callable $handler Function that receives (array $arguments, ?ExecutionContext $context) and returns ToolResult.
     */
    public function register(ToolInfo $tool, callable $handler): self
    {
        $this->tools[$tool->name] = $tool;
        $this->handlers[$tool->name] = $handler;

        return $this;
    }

    /**
     * Registers multiple tools at once.
     *
     * @param array<array{tool: ToolInfo, handler: callable}> $tools
     */
    public function registerMany(array $tools): self
    {
        foreach ($tools as $item) {
            $this->register($item['tool'], $item['handler']);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getTools(): array
    {
        return $this->tools;
    }

    /**
     * {@inheritdoc}
     */
    public function getTool(string $toolName): ?ToolInfo
    {
        return $this->tools[$toolName] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(string $toolName, array $arguments, ?ExecutionContext $context = null): ToolResult
    {
        if (!isset($this->handlers[$toolName])) {
            throw new ToolNotFoundException($toolName);
        }

        $handler = $this->handlers[$toolName];

        try {
            $result = $handler($arguments, $context);

            if (!$result instanceof ToolResult) {
                // Allow handlers to return arrays for convenience.
                if (is_array($result)) {
                    $success = $result['success'] ?? true;
                    $message = $result['message'] ?? ($success ? 'OK' : 'Failed');
                    unset($result['success'], $result['message']);

                    return $success
                        ? ToolResult::success($message, $result)
                        : ToolResult::error($message, $result);
                }

                // String results become success messages.
                if (is_string($result)) {
                    return ToolResult::success($result);
                }

                return ToolResult::success('OK', ['result' => $result]);
            }

            return $result;
        } catch (ToolExecutionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ToolExecutionException(
                $toolName,
                $e->getMessage(),
                ['exception' => get_class($e)],
                (int) $e->getCode(),
                $e,
            );
        }
    }
}
