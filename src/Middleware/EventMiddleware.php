<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway\Middleware;

use CodeWheel\McpToolGateway\Event\ToolExecutionFailed;
use CodeWheel\McpToolGateway\Event\ToolExecutionStarted;
use CodeWheel\McpToolGateway\Event\ToolExecutionSucceeded;
use CodeWheel\McpToolGateway\ExecutionContext;
use CodeWheel\McpToolGateway\ToolResult;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Dispatches events for tool execution lifecycle.
 *
 * Events dispatched:
 * - ToolExecutionStarted: Before execution begins
 * - ToolExecutionSucceeded: After successful execution
 * - ToolExecutionFailed: After failed execution or exception
 */
final class EventMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
    ) {}

    public function process(
        string $toolName,
        array $arguments,
        ExecutionContext $context,
        callable $next,
    ): ToolResult {
        $startTime = microtime(true);

        // Dispatch started event
        $this->dispatcher->dispatch(new ToolExecutionStarted(
            toolName: $toolName,
            arguments: $arguments,
            context: $context,
            timestamp: $startTime,
        ));

        try {
            $result = $next($toolName, $arguments, $context);
            $endTime = microtime(true);

            if ($result->success) {
                $this->dispatcher->dispatch(new ToolExecutionSucceeded(
                    toolName: $toolName,
                    arguments: $arguments,
                    result: $result,
                    context: $context,
                    startTime: $startTime,
                    endTime: $endTime,
                ));
            } else {
                $this->dispatcher->dispatch(new ToolExecutionFailed(
                    toolName: $toolName,
                    arguments: $arguments,
                    error: $result->message,
                    context: $context,
                    startTime: $startTime,
                    endTime: $endTime,
                ));
            }

            return $result;
        } catch (\Throwable $e) {
            $endTime = microtime(true);

            $this->dispatcher->dispatch(new ToolExecutionFailed(
                toolName: $toolName,
                arguments: $arguments,
                error: $e->getMessage(),
                context: $context,
                startTime: $startTime,
                endTime: $endTime,
                exception: $e,
            ));

            throw $e;
        }
    }
}
