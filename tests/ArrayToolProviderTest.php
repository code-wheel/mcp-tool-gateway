<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway\Tests;

use CodeWheel\McpToolGateway\ArrayToolProvider;
use CodeWheel\McpToolGateway\ExecutionContext;
use CodeWheel\McpToolGateway\ToolExecutionException;
use CodeWheel\McpToolGateway\ToolInfo;
use CodeWheel\McpToolGateway\ToolNotFoundException;
use CodeWheel\McpToolGateway\ToolResult;
use PHPUnit\Framework\TestCase;

class ArrayToolProviderTest extends TestCase
{
    public function testRegisterAndGetTools(): void
    {
        $provider = new ArrayToolProvider();

        $tool1 = new ToolInfo('tool-1', 'Tool 1', 'First tool');
        $tool2 = new ToolInfo('tool-2', 'Tool 2', 'Second tool');

        $provider->register($tool1, fn() => ToolResult::success('OK'));
        $provider->register($tool2, fn() => ToolResult::success('OK'));

        $tools = $provider->getTools();

        $this->assertCount(2, $tools);
        $this->assertArrayHasKey('tool-1', $tools);
        $this->assertArrayHasKey('tool-2', $tools);
    }

    public function testGetTool(): void
    {
        $provider = new ArrayToolProvider();
        $tool = new ToolInfo('my-tool', 'My Tool', 'Description');

        $provider->register($tool, fn() => ToolResult::success('OK'));

        $retrieved = $provider->getTool('my-tool');
        $this->assertSame($tool, $retrieved);

        $missing = $provider->getTool('non-existent');
        $this->assertNull($missing);
    }

    public function testExecuteWithToolResult(): void
    {
        $provider = new ArrayToolProvider();
        $tool = new ToolInfo('echo', 'Echo', 'Echoes input');

        $provider->register($tool, function (array $args): ToolResult {
            return ToolResult::success("Echo: {$args['message']}", ['input' => $args['message']]);
        });

        $result = $provider->execute('echo', ['message' => 'Hello']);

        $this->assertTrue($result->success);
        $this->assertSame('Echo: Hello', $result->message);
        $this->assertSame(['input' => 'Hello'], $result->data);
    }

    public function testExecuteWithArrayReturn(): void
    {
        $provider = new ArrayToolProvider();
        $tool = new ToolInfo('array-tool', 'Array Tool', 'Returns array');

        $provider->register($tool, fn() => ['success' => true, 'message' => 'Array result', 'foo' => 'bar']);

        $result = $provider->execute('array-tool', []);

        $this->assertTrue($result->success);
        $this->assertSame('Array result', $result->message);
        $this->assertSame(['foo' => 'bar'], $result->data);
    }

    public function testExecuteWithStringReturn(): void
    {
        $provider = new ArrayToolProvider();
        $tool = new ToolInfo('string-tool', 'String Tool', 'Returns string');

        $provider->register($tool, fn() => 'Simple string result');

        $result = $provider->execute('string-tool', []);

        $this->assertTrue($result->success);
        $this->assertSame('Simple string result', $result->message);
    }

    public function testExecuteWithContext(): void
    {
        $provider = new ArrayToolProvider();
        $tool = new ToolInfo('context-tool', 'Context Tool', 'Uses context');

        $provider->register($tool, function (array $args, ?ExecutionContext $context): ToolResult {
            $userId = $context?->userId ?? 'anonymous';
            return ToolResult::success("User: {$userId}");
        });

        $context = new ExecutionContext(userId: 'user-123');
        $result = $provider->execute('context-tool', [], $context);

        $this->assertSame('User: user-123', $result->message);
    }

    public function testExecuteThrowsNotFoundException(): void
    {
        $provider = new ArrayToolProvider();

        $this->expectException(ToolNotFoundException::class);
        $provider->execute('non-existent', []);
    }

    public function testExecuteWrapsExceptions(): void
    {
        $provider = new ArrayToolProvider();
        $tool = new ToolInfo('failing-tool', 'Failing Tool', 'Always fails');

        $provider->register($tool, function (): never {
            throw new \RuntimeException('Intentional failure');
        });

        $this->expectException(ToolExecutionException::class);
        $this->expectExceptionMessage('Intentional failure');
        $provider->execute('failing-tool', []);
    }

    public function testRegisterMany(): void
    {
        $provider = new ArrayToolProvider();

        $provider->registerMany([
            ['tool' => new ToolInfo('a', 'A', 'Tool A'), 'handler' => fn() => 'A'],
            ['tool' => new ToolInfo('b', 'B', 'Tool B'), 'handler' => fn() => 'B'],
            ['tool' => new ToolInfo('c', 'C', 'Tool C'), 'handler' => fn() => 'C'],
        ]);

        $this->assertCount(3, $provider->getTools());
    }

    public function testFluentInterface(): void
    {
        $provider = new ArrayToolProvider();

        $result = $provider
            ->register(new ToolInfo('a', 'A', 'A'), fn() => 'A')
            ->register(new ToolInfo('b', 'B', 'B'), fn() => 'B');

        $this->assertSame($provider, $result);
        $this->assertCount(2, $provider->getTools());
    }

    public function testConstructorWithInitialTools(): void
    {
        $tool1 = new ToolInfo('tool-1', 'Tool 1', 'First tool');
        $tool2 = new ToolInfo('tool-2', 'Tool 2', 'Second tool');

        $provider = new ArrayToolProvider([
            'tool-1' => $tool1,
            'tool-2' => $tool2,
        ]);

        $tools = $provider->getTools();
        $this->assertCount(2, $tools);
        $this->assertSame($tool1, $tools['tool-1']);
        $this->assertSame($tool2, $tools['tool-2']);
    }

    public function testSetHandler(): void
    {
        $tool = new ToolInfo('delayed-tool', 'Delayed Tool', 'Handler set later');
        $provider = new ArrayToolProvider(['delayed-tool' => $tool]);

        $result = $provider->setHandler('delayed-tool', fn(array $args) => ToolResult::success('Delayed: ' . ($args['msg'] ?? '')));

        $this->assertSame($provider, $result);

        $execResult = $provider->execute('delayed-tool', ['msg' => 'hello']);
        $this->assertTrue($execResult->success);
        $this->assertSame('Delayed: hello', $execResult->message);
    }

    public function testExecuteWithArbitraryReturn(): void
    {
        $provider = new ArrayToolProvider();
        $tool = new ToolInfo('object-tool', 'Object Tool', 'Returns arbitrary value');

        // Handler returns an integer
        $provider->register($tool, fn() => 42);

        $result = $provider->execute('object-tool', []);

        $this->assertTrue($result->success);
        $this->assertSame('OK', $result->message);
        $this->assertSame(['result' => 42], $result->data);
    }

    public function testExecuteWithFailedArrayReturn(): void
    {
        $provider = new ArrayToolProvider();
        $tool = new ToolInfo('fail-array', 'Fail Array', 'Returns failure array');

        $provider->register($tool, fn() => ['success' => false, 'message' => 'Failed operation', 'reason' => 'test']);

        $result = $provider->execute('fail-array', []);

        $this->assertFalse($result->success);
        $this->assertSame('Failed operation', $result->message);
        $this->assertSame(['reason' => 'test'], $result->data);
    }

    public function testExecuteWithArraySuccessWithoutMessage(): void
    {
        $provider = new ArrayToolProvider();
        $tool = new ToolInfo('array-no-msg', 'Array No Msg', 'Array without message');

        $provider->register($tool, fn() => ['success' => true, 'data' => 'value']);

        $result = $provider->execute('array-no-msg', []);

        $this->assertTrue($result->success);
        $this->assertSame('OK', $result->message);
    }

    public function testExecuteWithArrayFailureWithoutMessage(): void
    {
        $provider = new ArrayToolProvider();
        $tool = new ToolInfo('array-fail-no-msg', 'Array Fail No Msg', 'Failure without message');

        $provider->register($tool, fn() => ['success' => false]);

        $result = $provider->execute('array-fail-no-msg', []);

        $this->assertFalse($result->success);
        $this->assertSame('Failed', $result->message);
    }

    public function testExecutePreservesToolExecutionException(): void
    {
        $provider = new ArrayToolProvider();
        $tool = new ToolInfo('exec-exception', 'Exec Exception', 'Throws ToolExecutionException');

        $provider->register($tool, function (): never {
            throw new ToolExecutionException('exec-exception', 'Custom error', ['detail' => 'info']);
        });

        try {
            $provider->execute('exec-exception', []);
            $this->fail('Expected ToolExecutionException');
        } catch (ToolExecutionException $e) {
            $this->assertSame('Custom error', $e->getMessage());
            $this->assertSame('exec-exception', $e->getToolName());
        }
    }
}
