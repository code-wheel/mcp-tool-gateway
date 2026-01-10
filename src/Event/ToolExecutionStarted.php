<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway\Event;

use CodeWheel\McpToolGateway\ExecutionContext;

/**
 * Dispatched when tool execution begins.
 *
 * Use this event for:
 * - Logging/tracing
 * - Starting performance timers
 * - Rate limiting checks
 * - Request correlation
 */
final class ToolExecutionStarted
{
    /**
     * @param string $toolName The tool being executed.
     * @param array<string, mixed> $arguments The tool arguments.
     * @param ExecutionContext $context Execution context.
     * @param float $timestamp UNIX timestamp (microtime) when execution started.
     */
    public function __construct(
        public readonly string $toolName,
        public readonly array $arguments,
        public readonly ExecutionContext $context,
        public readonly float $timestamp,
    ) {}

    /**
     * Gets the duration since this event was created.
     */
    public function getElapsedMs(): float
    {
        return (microtime(true) - $this->timestamp) * 1000;
    }

    /**
     * Converts to array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event' => 'tool_execution_started',
            'tool_name' => $this->toolName,
            'request_id' => $this->context->requestId,
            'timestamp' => $this->timestamp,
        ];
    }
}
