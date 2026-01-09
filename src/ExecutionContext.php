<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway;

/**
 * Context information for tool execution.
 */
final class ExecutionContext
{
    /**
     * @param string|null $userId User/session identifier.
     * @param array<string, mixed> $attributes Additional context attributes.
     */
    public function __construct(
        public readonly ?string $userId = null,
        public readonly array $attributes = [],
    ) {}

    /**
     * Gets a context attribute.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Returns a new context with an added attribute.
     */
    public function with(string $key, mixed $value): self
    {
        return new self(
            userId: $this->userId,
            attributes: [...$this->attributes, $key => $value],
        );
    }
}
