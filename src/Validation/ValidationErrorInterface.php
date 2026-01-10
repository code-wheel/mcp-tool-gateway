<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway\Validation;

/**
 * Interface for individual validation errors.
 */
interface ValidationErrorInterface
{
    /**
     * Returns the JSON path to the invalid value (e.g., "/foo/bar/0").
     */
    public function getPath(): string;

    /**
     * Returns the error message.
     */
    public function getMessage(): string;

    /**
     * Returns an error code/type (e.g., "type", "required", "format").
     */
    public function getCode(): string;
}
