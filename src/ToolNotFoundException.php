<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway;

/**
 * Exception thrown when a requested tool is not found.
 */
class ToolNotFoundException extends \RuntimeException
{
    public function __construct(
        public readonly string $toolName,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $message = $message ?: "Tool not found: {$toolName}";
        parent::__construct($message, $code, $previous);
    }
}
