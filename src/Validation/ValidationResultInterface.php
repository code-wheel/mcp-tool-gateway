<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway\Validation;

/**
 * Interface for validation results returned by schema validators.
 *
 * This interface defines the contract for validation results used by
 * ValidatingMiddleware. Implementations should be provided by schema
 * validation packages (e.g., mcp-schema-builder).
 */
interface ValidationResultInterface
{
    /**
     * Returns true if validation passed.
     */
    public function isValid(): bool;

    /**
     * Returns validation errors.
     *
     * @return list<ValidationErrorInterface>
     */
    public function getErrors(): array;
}
