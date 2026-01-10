<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway\Tests;

use CodeWheel\McpToolGateway\ArrayToolProvider;
use CodeWheel\McpToolGateway\ExecutionContext;
use CodeWheel\McpToolGateway\Middleware\MiddlewarePipeline;
use CodeWheel\McpToolGateway\Middleware\ValidatingMiddleware;
use CodeWheel\McpToolGateway\ToolInfo;
use CodeWheel\McpToolGateway\ToolResult;
use CodeWheel\McpToolGateway\Validation\ValidationErrorInterface;
use CodeWheel\McpToolGateway\Validation\ValidationResultInterface;
use CodeWheel\McpToolGateway\Validation\ValidatorInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CodeWheel\McpToolGateway\Middleware\ValidatingMiddleware
 */
final class ValidatingMiddlewareTest extends TestCase
{
    private ArrayToolProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new ArrayToolProvider([
            'create_user' => new ToolInfo(
                name: 'create_user',
                label: 'Create User',
                description: 'Creates a new user',
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'minLength' => 1],
                        'email' => ['type' => 'string', 'format' => 'email'],
                        'age' => ['type' => 'integer', 'minimum' => 0],
                    ],
                    'required' => ['name', 'email'],
                ],
            ),
            'no_schema_tool' => new ToolInfo(
                name: 'no_schema_tool',
                label: 'No Schema',
                description: 'Tool without schema',
            ),
        ]);

        $this->provider->setHandler('create_user', fn(array $args) => ToolResult::success('Created'));
        $this->provider->setHandler('no_schema_tool', fn(array $args) => ToolResult::success('Done'));
    }

    public function testValidInputPassesThrough(): void
    {
        $validator = $this->createMockValidator(true, []);
        $middleware = new ValidatingMiddleware($this->provider, $validator);

        $pipeline = new MiddlewarePipeline($this->provider);
        $pipeline->add($middleware);

        $result = $pipeline->execute('create_user', [
            'name' => 'John',
            'email' => 'john@example.com',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame('Created', $result->message);
    }

    public function testInvalidInputReturnsError(): void
    {
        $errors = [
            $this->createMockError('email', 'Invalid email format', 'format'),
            $this->createMockError('age', 'Must be >= 0', 'minimum'),
        ];
        $validator = $this->createMockValidator(false, $errors);
        $middleware = new ValidatingMiddleware($this->provider, $validator);

        $pipeline = new MiddlewarePipeline($this->provider);
        $pipeline->add($middleware);

        $result = $pipeline->execute('create_user', [
            'name' => 'John',
            'email' => 'invalid',
            'age' => -5,
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Validation failed', $result->message);
        $this->assertStringContainsString('email', $result->message);
        $this->assertArrayHasKey('validation_errors', $result->data);
        $this->assertCount(2, $result->data['validation_errors']);
    }

    public function testMissingRequiredFieldReturnsError(): void
    {
        $errors = [
            $this->createMockError('email', 'Required field missing', 'required'),
        ];
        $validator = $this->createMockValidator(false, $errors);
        $middleware = new ValidatingMiddleware($this->provider, $validator);

        $pipeline = new MiddlewarePipeline($this->provider);
        $pipeline->add($middleware);

        $result = $pipeline->execute('create_user', ['name' => 'John']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('email', $result->message);
    }

    public function testToolWithoutSchemaPassesThrough(): void
    {
        $validator = $this->createMockValidator(true, []);
        $middleware = new ValidatingMiddleware($this->provider, $validator);

        $pipeline = new MiddlewarePipeline($this->provider);
        $pipeline->add($middleware);

        $result = $pipeline->execute('no_schema_tool', ['any' => 'data']);

        $this->assertTrue($result->success);
        $this->assertSame('Done', $result->message);
    }

    public function testUnknownToolPassesToNextHandler(): void
    {
        $validator = $this->createMockValidator(true, []);
        $middleware = new ValidatingMiddleware($this->provider, $validator);

        $pipeline = new MiddlewarePipeline($this->provider);
        $pipeline->add($middleware);

        // Unknown tool should pass through to provider which will throw
        $this->expectException(\CodeWheel\McpToolGateway\ToolNotFoundException::class);
        $pipeline->execute('unknown_tool', []);
    }

    public function testStrictModeRejectsUnknownProperties(): void
    {
        // In strict mode, the schema should have additionalProperties: false
        $capturedSchema = null;
        $validator = new class ($capturedSchema) implements ValidatorInterface {
            public function __construct(private ?array &$captured) {}

            public function validate(array $data, array $schema): ValidationResultInterface
            {
                $this->captured = $schema;
                return new class implements ValidationResultInterface {
                    public function isValid(): bool { return true; }
                    public function getErrors(): array { return []; }
                };
            }
        };

        $middleware = new ValidatingMiddleware($this->provider, $validator, strictMode: true);
        $pipeline = new MiddlewarePipeline($this->provider);
        $pipeline->add($middleware);

        $pipeline->execute('create_user', ['name' => 'John', 'email' => 'j@e.com']);

        $this->assertNotNull($capturedSchema);
        $this->assertFalse($capturedSchema['additionalProperties']);
    }

    public function testValidationErrorsIncludeAllDetails(): void
    {
        $errors = [
            $this->createMockError('name', 'Too short', 'minLength'),
        ];
        $validator = $this->createMockValidator(false, $errors);
        $middleware = new ValidatingMiddleware($this->provider, $validator);

        $pipeline = new MiddlewarePipeline($this->provider);
        $pipeline->add($middleware);

        $result = $pipeline->execute('create_user', ['name' => '', 'email' => 'a@b.com']);

        $this->assertFalse($result->success);
        $validationErrors = $result->data['validation_errors'];
        $this->assertSame('name', $validationErrors[0]['path']);
        $this->assertSame('Too short', $validationErrors[0]['message']);
        $this->assertSame('minLength', $validationErrors[0]['code']);
    }

    public function testValidationErrorWithEmptyPath(): void
    {
        // Some validators return empty path for root-level errors
        $errors = [
            $this->createMockError('', 'Object is invalid', 'type'),
        ];
        $validator = $this->createMockValidator(false, $errors);
        $middleware = new ValidatingMiddleware($this->provider, $validator);

        $pipeline = new MiddlewarePipeline($this->provider);
        $pipeline->add($middleware);

        $result = $pipeline->execute('create_user', ['name' => 'John', 'email' => 'j@e.com']);

        $this->assertFalse($result->success);
        // Message should not have ": " prefix when path is empty
        $this->assertStringContainsString('Object is invalid', $result->message);
        $this->assertStringNotContainsString(': Object is invalid', $result->message);
    }

    public function testStrictModeNotAppliedToNonObjectSchema(): void
    {
        // Create a tool with non-object schema
        $this->provider = new ArrayToolProvider([
            'string_tool' => new ToolInfo(
                name: 'string_tool',
                label: 'String Tool',
                description: 'Takes a string',
                inputSchema: [
                    'type' => 'string',
                ],
            ),
        ]);
        $this->provider->setHandler('string_tool', fn() => ToolResult::success('Done'));

        $capturedSchema = null;
        $validator = new class ($capturedSchema) implements ValidatorInterface {
            public function __construct(private ?array &$captured) {}

            public function validate(array $data, array $schema): ValidationResultInterface
            {
                $this->captured = $schema;
                return new class implements ValidationResultInterface {
                    public function isValid(): bool { return true; }
                    public function getErrors(): array { return []; }
                };
            }
        };

        $middleware = new ValidatingMiddleware($this->provider, $validator, strictMode: true);
        $pipeline = new MiddlewarePipeline($this->provider);
        $pipeline->add($middleware);

        $pipeline->execute('string_tool', []);

        $this->assertNotNull($capturedSchema);
        // additionalProperties should NOT be added for non-object types
        $this->assertArrayNotHasKey('additionalProperties', $capturedSchema);
    }

    /**
     * Creates a mock validator that returns the specified result.
     */
    private function createMockValidator(bool $isValid, array $errors): ValidatorInterface
    {
        return new class ($isValid, $errors) implements ValidatorInterface {
            public function __construct(
                private bool $isValid,
                private array $errors,
            ) {}

            public function validate(array $data, array $schema): ValidationResultInterface
            {
                $isValid = $this->isValid;
                $errors = $this->errors;

                return new class ($isValid, $errors) implements ValidationResultInterface {
                    public function __construct(
                        private bool $valid,
                        private array $errs,
                    ) {}

                    public function isValid(): bool
                    {
                        return $this->valid;
                    }

                    public function getErrors(): array
                    {
                        return $this->errs;
                    }
                };
            }
        };
    }

    /**
     * Creates a mock validation error.
     */
    private function createMockError(string $path, string $message, string $code): ValidationErrorInterface
    {
        return new class ($path, $message, $code) implements ValidationErrorInterface {
            public function __construct(
                private string $path,
                private string $message,
                private string $code,
            ) {}

            public function getPath(): string { return $this->path; }
            public function getMessage(): string { return $this->message; }
            public function getCode(): string { return $this->code; }
        };
    }
}
