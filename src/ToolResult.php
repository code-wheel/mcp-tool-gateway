<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway;

/**
 * Represents the result of a tool execution.
 */
final class ToolResult
{
    /**
     * @param bool $success Whether the execution succeeded.
     * @param string $message Human-readable result message.
     * @param array<string, mixed> $data Structured result data.
     * @param bool $isError Whether this represents an error.
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly array $data = [],
        public readonly bool $isError = false,
    ) {}

    /**
     * Creates a successful result.
     *
     * @param string $message Success message.
     * @param array<string, mixed> $data Result data.
     */
    public static function success(string $message, array $data = []): self
    {
        return new self(
            success: true,
            message: $message,
            data: $data,
            isError: false,
        );
    }

    /**
     * Creates an error result.
     *
     * @param string $message Error message.
     * @param array<string, mixed> $data Additional error context.
     */
    public static function error(string $message, array $data = []): self
    {
        return new self(
            success: false,
            message: $message,
            data: $data,
            isError: true,
        );
    }

    /**
     * Converts to a structured array suitable for MCP responses.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            ...$this->data,
        ];
    }
}
