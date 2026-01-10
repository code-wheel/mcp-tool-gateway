<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway\Validation;

/**
 * Interface for JSON Schema validators.
 *
 * Implementations should validate data against a JSON Schema and return
 * a ValidationResultInterface with any errors found.
 */
interface ValidatorInterface
{
    /**
     * Validates data against a JSON Schema.
     *
     * @param array<string, mixed> $data The data to validate.
     * @param array<string, mixed> $schema The JSON Schema to validate against.
     * @return ValidationResultInterface The validation result.
     */
    public function validate(array $data, array $schema): ValidationResultInterface;
}
