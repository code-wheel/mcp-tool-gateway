<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway\Tests;

use CodeWheel\McpToolGateway\ArrayToolProvider;
use CodeWheel\McpToolGateway\ExecutionContext;
use CodeWheel\McpToolGateway\Middleware\MiddlewareInterface;
use CodeWheel\McpToolGateway\Middleware\MiddlewarePipeline;
use CodeWheel\McpToolGateway\ToolInfo;
use CodeWheel\McpToolGateway\ToolResult;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CodeWheel\McpToolGateway\Middleware\MiddlewarePipeline
 */
final class MiddlewarePipelineTest extends TestCase
{
    private ArrayToolProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new ArrayToolProvider([
            'test_tool' => new ToolInfo(
                name: 'test_tool',
                label: 'Test Tool',
                description: 'A test tool',
            ),
        ]);

        $this->provider->setHandler('test_tool', function (array $args): ToolResult {
            return ToolResult::success('Executed with: ' . json_encode($args), $args);
        });
    }

    public function testExecuteWithoutMiddleware(): void
    {
        $pipeline = new MiddlewarePipeline($this->provider);
        $result = $pipeline->execute('test_tool', ['foo' => 'bar']);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('foo', $result->message);
    }

    public function testMiddlewareIsExecutedInOrder(): void
    {
        $order = [];

        $first = $this->createMiddleware(function () use (&$order) {
            $order[] = 'first_before';
        }, function () use (&$order) {
            $order[] = 'first_after';
        });

        $second = $this->createMiddleware(function () use (&$order) {
            $order[] = 'second_before';
        }, function () use (&$order) {
            $order[] = 'second_after';
        });

        $pipeline = new MiddlewarePipeline($this->provider);
        $pipeline->add($first);
        $pipeline->add($second);

        $pipeline->execute('test_tool', []);

        $this->assertSame(['first_before', 'second_before', 'second_after', 'first_after'], $order);
    }

    public function testMiddlewareCanModifyArguments(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(
                string $toolName,
                array $arguments,
                ExecutionContext $context,
                callable $next,
            ): ToolResult {
                $arguments['injected'] = 'value';
                return $next($toolName, $arguments, $context);
            }
        };

        $pipeline = new MiddlewarePipeline($this->provider);
        $pipeline->add($middleware);

        $result = $pipeline->execute('test_tool', ['original' => 'data']);

        $this->assertTrue($result->success);
        $this->assertSame('value', $result->data['injected'] ?? null);
    }

    public function testMiddlewareCanShortCircuit(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(
                string $toolName,
                array $arguments,
                ExecutionContext $context,
                callable $next,
            ): ToolResult {
                return ToolResult::error('Blocked by middleware');
            }
        };

        $pipeline = new MiddlewarePipeline($this->provider);
        $pipeline->add($middleware);

        $result = $pipeline->execute('test_tool', []);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Blocked', $result->message);
    }

    public function testMiddlewareCanWrapExceptions(): void
    {
        $this->provider->setHandler('test_tool', function (): ToolResult {
            throw new \RuntimeException('Tool failed');
        });

        $caught = false;
        $middleware = new class ($caught) implements MiddlewareInterface {
            public function __construct(private bool &$caught) {}

            public function process(
                string $toolName,
                array $arguments,
                ExecutionContext $context,
                callable $next,
            ): ToolResult {
                try {
                    return $next($toolName, $arguments, $context);
                } catch (\RuntimeException $e) {
                    $this->caught = true;
                    return ToolResult::error('Caught: ' . $e->getMessage());
                }
            }
        };

        $pipeline = new MiddlewarePipeline($this->provider);
        $pipeline->add($middleware);

        $result = $pipeline->execute('test_tool', []);

        $this->assertTrue($caught);
        $this->assertFalse($result->success);
        $this->assertStringContainsString('Caught', $result->message);
    }

    public function testContextIsPassedThrough(): void
    {
        $capturedContext = null;

        $middleware = new class ($capturedContext) implements MiddlewareInterface {
            public function __construct(private ?ExecutionContext &$captured) {}

            public function process(
                string $toolName,
                array $arguments,
                ExecutionContext $context,
                callable $next,
            ): ToolResult {
                $this->captured = $context;
                return $next($toolName, $arguments, $context);
            }
        };

        $pipeline = new MiddlewarePipeline($this->provider);
        $pipeline->add($middleware);

        $context = ExecutionContext::create(userId: 'user-123', scopes: ['read']);
        $pipeline->execute('test_tool', [], $context);

        $this->assertNotNull($capturedContext);
        $this->assertSame('user-123', $capturedContext->userId);
        $this->assertTrue($capturedContext->hasScope('read'));
    }

    public function testAddMany(): void
    {
        $count = 0;

        $middleware1 = $this->createMiddleware(function () use (&$count) { $count++; });
        $middleware2 = $this->createMiddleware(function () use (&$count) { $count++; });

        $pipeline = new MiddlewarePipeline($this->provider);
        $pipeline->addMany([$middleware1, $middleware2]);

        $this->assertSame(2, $pipeline->count());

        $pipeline->execute('test_tool', []);
        $this->assertSame(2, $count);
    }

    public function testClear(): void
    {
        $pipeline = new MiddlewarePipeline($this->provider);
        $pipeline->add($this->createMiddleware());
        $pipeline->add($this->createMiddleware());

        $this->assertSame(2, $pipeline->count());

        $pipeline->clear();

        $this->assertSame(0, $pipeline->count());
    }

    public function testExecuteCreatesDefaultContextWhenNoneProvided(): void
    {
        $capturedContext = null;

        $middleware = new class ($capturedContext) implements MiddlewareInterface {
            public function __construct(private ?ExecutionContext &$captured) {}

            public function process(
                string $toolName,
                array $arguments,
                ExecutionContext $context,
                callable $next,
            ): ToolResult {
                $this->captured = $context;
                return $next($toolName, $arguments, $context);
            }
        };

        $pipeline = new MiddlewarePipeline($this->provider);
        $pipeline->add($middleware);

        // Execute without providing context
        $result = $pipeline->execute('test_tool', []);

        $this->assertTrue($result->success);
        $this->assertNotNull($capturedContext);
        $this->assertInstanceOf(ExecutionContext::class, $capturedContext);
    }

    public function testAddReturnsFluentInterface(): void
    {
        $pipeline = new MiddlewarePipeline($this->provider);

        $result = $pipeline->add($this->createMiddleware());

        $this->assertSame($pipeline, $result);
    }

    public function testAddManyReturnsFluentInterface(): void
    {
        $pipeline = new MiddlewarePipeline($this->provider);

        $result = $pipeline->addMany([$this->createMiddleware()]);

        $this->assertSame($pipeline, $result);
    }

    public function testClearReturnsFluentInterface(): void
    {
        $pipeline = new MiddlewarePipeline($this->provider);
        $pipeline->add($this->createMiddleware());

        $result = $pipeline->clear();

        $this->assertSame($pipeline, $result);
    }

    /**
     * Creates a simple middleware for testing.
     */
    private function createMiddleware(
        ?callable $before = null,
        ?callable $after = null,
    ): MiddlewareInterface {
        return new class ($before, $after) implements MiddlewareInterface {
            /** @var callable|null */
            private $before;
            /** @var callable|null */
            private $after;

            public function __construct(?callable $before, ?callable $after)
            {
                $this->before = $before;
                $this->after = $after;
            }

            public function process(
                string $toolName,
                array $arguments,
                ExecutionContext $context,
                callable $next,
            ): ToolResult {
                if ($this->before !== null) {
                    ($this->before)();
                }

                $result = $next($toolName, $arguments, $context);

                if ($this->after !== null) {
                    ($this->after)();
                }

                return $result;
            }
        };
    }
}
