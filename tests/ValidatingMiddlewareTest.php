<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway\Tests;

use CodeWheel\McpToolGateway\ArrayToolProvider;
use CodeWheel\McpToolGateway\ExecutionContext;
use CodeWheel\McpToolGateway\Middleware\MiddlewarePipeline;
use CodeWheel\McpToolGateway\Middleware\ValidatingMiddleware;
use CodeWheel\McpToolGateway\ToolInfo;
use CodeWheel\McpToolGateway\ToolResult;
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
            (object)['path' => 'email', 'message' => 'Invalid email format', 'code' => 'format'],
            (object)['path' => 'age', 'message' => 'Must be >= 0', 'code' => 'minimum'],
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
            (object)['path' => 'email', 'message' => 'Required field missing', 'code' => 'required'],
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
        $validator = new class ($capturedSchema) {
            public function __construct(private ?array &$captured) {}

            public function validate(array $data, array $schema): object
            {
                $this->captured = $schema;
                return new class {
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

    public function testConstructorRejectsInvalidValidator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('validate');

        new ValidatingMiddleware($this->provider, new \stdClass());
    }

    public function testValidationErrorsIncludeAllDetails(): void
    {
        $errors = [
            (object)['path' => 'name', 'message' => 'Too short', 'code' => 'minLength'],
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

    /**
     * Creates a mock validator that returns the specified result.
     */
    private function createMockValidator(bool $isValid, array $errors): object
    {
        return new class ($isValid, $errors) {
            public function __construct(
                private bool $isValid,
                private array $errors,
            ) {}

            public function validate(array $data, array $schema): object
            {
                $isValid = $this->isValid;
                $errors = $this->errors;

                return new class ($isValid, $errors) {
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
}
