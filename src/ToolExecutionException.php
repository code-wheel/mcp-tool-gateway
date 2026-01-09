<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway;

/**
 * Exception thrown when tool execution fails.
 */
class ToolExecutionException extends \RuntimeException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public readonly string $toolName,
        string $message = '',
        public readonly array $context = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $message = $message ?: "Failed to execute tool: {$toolName}";
        parent::__construct($message, $code, $previous);
    }
}
