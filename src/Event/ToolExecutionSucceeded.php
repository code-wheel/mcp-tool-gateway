<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway\Event;

use CodeWheel\McpToolGateway\ExecutionContext;
use CodeWheel\McpToolGateway\ToolResult;

/**
 * Dispatched when tool execution completes successfully.
 *
 * Use this event for:
 * - Performance metrics
 * - Success logging
 * - Cache population
 * - Audit trails
 */
final class ToolExecutionSucceeded
{
    /**
     * @param string $toolName The tool that was executed.
     * @param array<string, mixed> $arguments The tool arguments.
     * @param ToolResult $result The execution result.
     * @param ExecutionContext $context Execution context.
     * @param float $startTime When execution started.
     * @param float $endTime When execution completed.
     */
    public function __construct(
        public readonly string $toolName,
        public readonly array $arguments,
        public readonly ToolResult $result,
        public readonly ExecutionContext $context,
        public readonly float $startTime,
        public readonly float $endTime,
    ) {}

    /**
     * Gets the execution duration in milliseconds.
     */
    public function getDurationMs(): float
    {
        return ($this->endTime - $this->startTime) * 1000;
    }

    /**
     * Converts to array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event' => 'tool_execution_succeeded',
            'tool_name' => $this->toolName,
            'request_id' => $this->context->requestId,
            'duration_ms' => $this->getDurationMs(),
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
        ];
    }

    /**
     * Converts to metrics format.
     *
     * @return array<string, mixed>
     */
    public function toMetrics(): array
    {
        return [
            'tool' => $this->toolName,
            'success' => true,
            'duration_ms' => $this->getDurationMs(),
        ];
    }
}
