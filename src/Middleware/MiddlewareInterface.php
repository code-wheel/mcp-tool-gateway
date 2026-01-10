<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway\Middleware;

use CodeWheel\McpToolGateway\ExecutionContext;
use CodeWheel\McpToolGateway\ToolResult;

/**
 * Interface for tool execution middleware.
 *
 * Middleware can intercept tool execution to add cross-cutting concerns like:
 * - Logging
 * - Authentication/Authorization
 * - Rate limiting
 * - Caching
 * - Validation
 * - Error handling
 * - Metrics collection
 *
 * Example:
 * ```php
 * class LoggingMiddleware implements MiddlewareInterface {
 *     public function process(
 *         string $toolName,
 *         array $arguments,
 *         ExecutionContext $context,
 *         callable $next
 *     ): ToolResult {
 *         $this->logger->info("Executing tool: $toolName");
 *         $start = microtime(true);
 *
 *         $result = $next($toolName, $arguments, $context);
 *
 *         $duration = microtime(true) - $start;
 *         $this->logger->info("Tool completed in {$duration}s");
 *
 *         return $result;
 *     }
 * }
 * ```
 */
interface MiddlewareInterface
{
    /**
     * Process the tool execution request.
     *
     * @param string $toolName The tool being executed.
     * @param array<string, mixed> $arguments The tool arguments.
     * @param ExecutionContext $context Execution context (user info, request ID, etc).
     * @param callable(string, array<string, mixed>, ExecutionContext): ToolResult $next
     *   The next handler in the pipeline.
     *
     * @return ToolResult The result of tool execution.
     */
    public function process(
        string $toolName,
        array $arguments,
        ExecutionContext $context,
        callable $next,
    ): ToolResult;
}
