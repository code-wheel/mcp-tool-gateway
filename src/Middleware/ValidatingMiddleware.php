<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway\Middleware;

use CodeWheel\McpToolGateway\ExecutionContext;
use CodeWheel\McpToolGateway\ToolProviderInterface;
use CodeWheel\McpToolGateway\ToolResult;
use CodeWheel\McpToolGateway\Validation\ValidatorInterface;

/**
 * Middleware that validates tool arguments against their JSON Schema.
 *
 * Rejects requests with invalid arguments before they reach the tool handler,
 * preventing malformed LLM-generated inputs from causing errors.
 *
 * Example:
 * ```php
 * use CodeWheel\McpSchemaBuilder\SchemaValidator;
 *
 * $validator = new SchemaValidator();
 * $middleware = new ValidatingMiddleware($provider, $validator);
 *
 * $pipeline = new MiddlewarePipeline($provider);
 * $pipeline->add($middleware);
 * ```
 */
final class ValidatingMiddleware implements MiddlewareInterface
{
    /**
     * @param ToolProviderInterface $provider Provider to look up tool schemas.
     * @param ValidatorInterface $validator Validator implementing ValidatorInterface.
     * @param bool $strictMode If true, reject unknown properties. Default false.
     */
    public function __construct(
        private readonly ToolProviderInterface $provider,
        private readonly ValidatorInterface $validator,
        private readonly bool $strictMode = false,
    ) {}

    public function process(
        string $toolName,
        array $arguments,
        ExecutionContext $context,
        callable $next,
    ): ToolResult {
        $tool = $this->provider->getTool($toolName);

        if ($tool === null) {
            // Let the next handler deal with unknown tools
            return $next($toolName, $arguments, $context);
        }

        $schema = $tool->inputSchema;

        // Skip validation if no schema defined
        if (empty($schema)) {
            return $next($toolName, $arguments, $context);
        }

        // Add strictMode to schema if enabled
        if ($this->strictMode && ($schema['type'] ?? '') === 'object') {
            $schema['additionalProperties'] = false;
        }

        $result = $this->validator->validate($arguments, $schema);

        if (!$result->isValid()) {
            $errors = $result->getErrors();
            $errorMessages = [];

            foreach ($errors as $error) {
                $path = $error->getPath();
                $message = $error->getMessage();
                $errorMessages[] = $path !== '' ? "{$path}: {$message}" : $message;
            }

            return ToolResult::error(
                'Validation failed: ' . implode('; ', $errorMessages),
                [
                    'validation_errors' => array_map(
                        fn($e) => [
                            'path' => $e->getPath(),
                            'message' => $e->getMessage(),
                            'code' => $e->getCode(),
                        ],
                        $errors
                    ),
                ]
            );
        }

        return $next($toolName, $arguments, $context);
    }
}
