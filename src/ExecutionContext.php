<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway;

/**
 * Context information for tool execution.
 *
 * Carries request-scoped information through the middleware pipeline:
 * - Request ID for correlation/tracing
 * - User ID for authorization
 * - Scopes for permission checking
 * - Custom attributes for middleware
 */
final class ExecutionContext
{
    /**
     * @param string|int|null $requestId Request ID for correlation.
     * @param string|null $userId User/session identifier.
     * @param string[] $scopes Authorization scopes.
     * @param array<string, mixed> $attributes Additional context attributes.
     */
    public function __construct(
        public readonly string|int|null $requestId = null,
        public readonly ?string $userId = null,
        public readonly array $scopes = [],
        public readonly array $attributes = [],
    ) {}

    /**
     * Creates a context with a generated request ID.
     *
     * @param list<string> $scopes
     * @param array<string, mixed> $attributes
     */
    public static function create(
        ?string $userId = null,
        array $scopes = [],
        array $attributes = [],
    ): self {
        return new self(
            requestId: uniqid('req_', true),
            userId: $userId,
            scopes: $scopes,
            attributes: $attributes,
        );
    }

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
            requestId: $this->requestId,
            userId: $this->userId,
            scopes: $this->scopes,
            attributes: [...$this->attributes, $key => $value],
        );
    }

    /**
     * Checks if a scope is present.
     */
    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    /**
     * Checks if any of the given scopes are present.
     *
     * @param string[] $scopes
     */
    public function hasAnyScope(array $scopes): bool
    {
        foreach ($scopes as $scope) {
            if ($this->hasScope($scope)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Converts to array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'request_id' => $this->requestId,
            'user_id' => $this->userId,
            'scopes' => $this->scopes,
            'attributes' => $this->attributes,
        ];
    }
}
