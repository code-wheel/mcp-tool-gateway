<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway\Tests;

use CodeWheel\McpToolGateway\ArrayToolProvider;
use CodeWheel\McpToolGateway\ExecutionContext;
use CodeWheel\McpToolGateway\ToolExecutionException;
use CodeWheel\McpToolGateway\ToolGateway;
use CodeWheel\McpToolGateway\ToolInfo;
use CodeWheel\McpToolGateway\ToolNotFoundException;
use CodeWheel\McpToolGateway\ToolResult;
use PHPUnit\Framework\TestCase;

class ToolGatewayTest extends TestCase
{
    private ArrayToolProvider $provider;
    private ToolGateway $gateway;

    protected function setUp(): void
    {
        $this->provider = new ArrayToolProvider();
        $this->provider->register(
            new ToolInfo('greet', 'Greet', 'Greets a user', ['type' => 'object'], ['readOnlyHint' => true]),
            fn(array $args) => ToolResult::success("Hello, {$args['name']}!")
        );
        $this->provider->register(
            new ToolInfo('add', 'Add Numbers', 'Adds two numbers'),
            fn(array $args) => ToolResult::success("Result: " . ($args['a'] + $args['b']), ['result' => $args['a'] + $args['b']])
        );
        $this->gateway = new ToolGateway($this->provider);
    }

    public function testGetGatewayToolsReturnsThreeTools(): void
    {
        $tools = $this->gateway->getGatewayTools();

        $this->assertCount(3, $tools);
        $this->assertSame('gateway/discover-tools', $tools[0]['name']);
        $this->assertSame('gateway/get-tool-info', $tools[1]['name']);
        $this->assertSame('gateway/execute-tool', $tools[2]['name']);
    }

    public function testGetGatewayToolsWithPrefix(): void
    {
        $gateway = new ToolGateway($this->provider, 'myapp');
        $tools = $gateway->getGatewayTools();

        $this->assertSame('myapp/gateway/discover-tools', $tools[0]['name']);
        $this->assertSame('myapp/gateway/get-tool-info', $tools[1]['name']);
        $this->assertSame('myapp/gateway/execute-tool', $tools[2]['name']);
    }

    public function testGetGatewayToolsHaveCorrectStructure(): void
    {
        $tools = $this->gateway->getGatewayTools();

        foreach ($tools as $tool) {
            $this->assertArrayHasKey('name', $tool);
            $this->assertArrayHasKey('description', $tool);
            $this->assertArrayHasKey('handler', $tool);
            $this->assertArrayHasKey('inputSchema', $tool);
            $this->assertArrayHasKey('annotations', $tool);
            $this->assertIsCallable($tool['handler']);
        }
    }

    public function testDiscoverToolsReturnsAllTools(): void
    {
        $result = $this->gateway->discoverTools();

        $this->assertFalse($result->isError);
        $this->assertSame(2, $result->structuredContent['count']);
        $this->assertCount(2, $result->structuredContent['tools']);
    }

    public function testDiscoverToolsWithQuery(): void
    {
        $result = $this->gateway->discoverTools('greet');

        $this->assertSame(1, $result->structuredContent['count']);
        $this->assertSame('greet', $result->structuredContent['tools'][0]['name']);
    }

    public function testDiscoverToolsWithQueryMatchesDescription(): void
    {
        $result = $this->gateway->discoverTools('numbers');

        $this->assertSame(1, $result->structuredContent['count']);
        $this->assertSame('add', $result->structuredContent['tools'][0]['name']);
    }

    public function testDiscoverToolsWithQueryNoMatches(): void
    {
        $result = $this->gateway->discoverTools('nonexistent');

        $this->assertSame(0, $result->structuredContent['count']);
        $this->assertEmpty($result->structuredContent['tools']);
    }

    public function testDiscoverToolsWithEmptyQuery(): void
    {
        $result = $this->gateway->discoverTools('');

        $this->assertSame(2, $result->structuredContent['count']);
    }

    public function testGetToolInfo(): void
    {
        $result = $this->gateway->getToolInfo('greet');

        $this->assertFalse($result->isError);
        $this->assertTrue($result->structuredContent['success']);
        $this->assertSame('greet', $result->structuredContent['name']);
        $this->assertSame('Greet', $result->structuredContent['label']);
    }

    public function testGetToolInfoForUnknownTool(): void
    {
        $result = $this->gateway->getToolInfo('unknown');

        $this->assertTrue($result->isError);
        $this->assertFalse($result->structuredContent['success']);
        $this->assertStringContainsString('Unknown tool', $result->structuredContent['error']);
    }

    public function testExecuteTool(): void
    {
        $result = $this->gateway->executeTool('greet', ['name' => 'World']);

        $this->assertFalse($result->isError);
        $this->assertTrue($result->structuredContent['success']);
        $this->assertSame('Hello, World!', $result->structuredContent['message']);
    }

    public function testExecuteToolWithContext(): void
    {
        $this->provider->register(
            new ToolInfo('context-tool', 'Context Tool', 'Uses context'),
            fn(array $args, ?ExecutionContext $ctx) => ToolResult::success("User: " . ($ctx?->userId ?? 'anonymous'))
        );

        $context = new ExecutionContext(userId: 'test-user');
        $result = $this->gateway->executeTool('context-tool', [], $context);

        $this->assertSame('User: test-user', $result->structuredContent['message']);
    }

    public function testExecuteToolNotFound(): void
    {
        $result = $this->gateway->executeTool('unknown', []);

        $this->assertTrue($result->isError);
        $this->assertFalse($result->structuredContent['success']);
        $this->assertStringContainsString('Unknown tool', $result->structuredContent['error']);
    }

    public function testExecuteToolHandlesToolNotFoundException(): void
    {
        $provider = $this->createMock(\CodeWheel\McpToolGateway\ToolProviderInterface::class);
        $provider->method('execute')->willThrowException(new ToolNotFoundException('test'));

        $gateway = new ToolGateway($provider);
        $result = $gateway->executeTool('test', []);

        $this->assertTrue($result->isError);
        $this->assertStringContainsString('Unknown tool', $result->structuredContent['error']);
    }

    public function testExecuteToolHandlesToolExecutionException(): void
    {
        $provider = $this->createMock(\CodeWheel\McpToolGateway\ToolProviderInterface::class);
        $provider->method('execute')->willThrowException(
            new ToolExecutionException('test', 'Custom error', ['extra' => 'data'])
        );

        $gateway = new ToolGateway($provider);
        $result = $gateway->executeTool('test', []);

        $this->assertTrue($result->isError);
        $this->assertSame('Custom error', $result->structuredContent['error']);
        $this->assertSame('data', $result->structuredContent['extra']);
    }

    public function testExecuteToolHandlesGenericException(): void
    {
        $provider = $this->createMock(\CodeWheel\McpToolGateway\ToolProviderInterface::class);
        $provider->method('execute')->willThrowException(new \RuntimeException('Something broke'));

        $gateway = new ToolGateway($provider);
        $result = $gateway->executeTool('test', []);

        $this->assertTrue($result->isError);
        $this->assertStringContainsString('Something broke', $result->structuredContent['error']);
    }

    public function testGatewayToolHandlersAreCallable(): void
    {
        $tools = $this->gateway->getGatewayTools();

        // Test discover handler
        $discoverResult = ($tools[0]['handler'])(null);
        $this->assertSame(2, $discoverResult->structuredContent['count']);

        // Test info handler
        $infoResult = ($tools[1]['handler'])('greet');
        $this->assertSame('greet', $infoResult->structuredContent['name']);

        // Test execute handler
        $executeResult = ($tools[2]['handler'])('greet', ['name' => 'Test'], null);
        $this->assertSame('Hello, Test!', $executeResult->structuredContent['message']);
    }

    public function testToolConstants(): void
    {
        $this->assertSame('gateway/discover-tools', ToolGateway::DISCOVER_TOOL);
        $this->assertSame('gateway/get-tool-info', ToolGateway::GET_INFO_TOOL);
        $this->assertSame('gateway/execute-tool', ToolGateway::EXECUTE_TOOL);
    }
}
