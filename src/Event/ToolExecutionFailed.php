<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway\Event;

use CodeWheel\McpToolGateway\ExecutionContext;

/**
 * Dispatched when tool execution fails.
 *
 * Use this event for:
 * - Error logging
 * - Alerting
 * - Error metrics
 * - Circuit breaker updates
 */
final class ToolExecutionFailed
{
    /**
     * @param string $toolName The tool that failed.
     * @param array<string, mixed> $arguments The tool arguments.
     * @param string $error Error message.
     * @param ExecutionContext $context Execution context.
     * @param float $startTime When execution started.
     * @param float $endTime When execution failed.
     * @param \Throwable|null $exception The exception if one was thrown.
     */
    public function __construct(
        public readonly string $toolName,
        public readonly array $arguments,
        public readonly string $error,
        public readonly ExecutionContext $context,
        public readonly float $startTime,
        public readonly float $endTime,
        public readonly ?\Throwable $exception = null,
    ) {}

    /**
     * Gets the execution duration in milliseconds.
     */
    public function getDurationMs(): float
    {
        return ($this->endTime - $this->startTime) * 1000;
    }

    /**
     * Checks if the failure was caused by an exception.
     */
    public function hasException(): bool
    {
        return $this->exception !== null;
    }

    /**
     * Converts to array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'event' => 'tool_execution_failed',
            'tool_name' => $this->toolName,
            'request_id' => $this->context->requestId,
            'error' => $this->error,
            'duration_ms' => $this->getDurationMs(),
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
        ];

        if ($this->exception !== null) {
            $data['exception_class'] = get_class($this->exception);
        }

        return $data;
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
            'success' => false,
            'duration_ms' => $this->getDurationMs(),
            'error_type' => $this->exception !== null ? get_class($this->exception) : 'tool_error',
        ];
    }
}
