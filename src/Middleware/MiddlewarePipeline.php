<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway\Middleware;

use CodeWheel\McpToolGateway\ExecutionContext;
use CodeWheel\McpToolGateway\ToolProviderInterface;
use CodeWheel\McpToolGateway\ToolResult;

/**
 * Chains middleware together for tool execution.
 *
 * Example:
 * ```php
 * $pipeline = new MiddlewarePipeline($provider);
 * $pipeline->add(new LoggingMiddleware($logger));
 * $pipeline->add(new RateLimitMiddleware($limiter));
 * $pipeline->add(new CachingMiddleware($cache));
 *
 * $result = $pipeline->execute('tool_name', $arguments, $context);
 * ```
 */
final class MiddlewarePipeline
{
    /** @var MiddlewareInterface[] */
    private array $middleware = [];

    public function __construct(
        private readonly ToolProviderInterface $provider,
    ) {}

    /**
     * Adds middleware to the pipeline.
     *
     * Middleware is executed in the order it's added.
     */
    public function add(MiddlewareInterface $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Adds multiple middleware at once.
     *
     * @param MiddlewareInterface[] $middleware
     */
    public function addMany(array $middleware): self
    {
        foreach ($middleware as $m) {
            $this->add($m);
        }
        return $this;
    }

    /**
     * Executes a tool through the middleware pipeline.
     *
     * @param array<string, mixed> $arguments
     */
    public function execute(
        string $toolName,
        array $arguments,
        ?ExecutionContext $context = null,
    ): ToolResult {
        $context = $context ?? new ExecutionContext();

        // Build the handler chain from inside out
        $handler = fn(string $name, array $args, ExecutionContext $ctx): ToolResult
            => $this->provider->execute($name, $args, $ctx);

        // Wrap with middleware in reverse order
        foreach (array_reverse($this->middleware) as $middleware) {
            $next = $handler;
            $handler = fn(string $name, array $args, ExecutionContext $ctx): ToolResult
                => $middleware->process($name, $args, $ctx, $next);
        }

        return $handler($toolName, $arguments, $context);
    }

    /**
     * Returns a count of middleware in the pipeline.
     */
    public function count(): int
    {
        return count($this->middleware);
    }

    /**
     * Clears all middleware from the pipeline.
     */
    public function clear(): self
    {
        $this->middleware = [];
        return $this;
    }
}
