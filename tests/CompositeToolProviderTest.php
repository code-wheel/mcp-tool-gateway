<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway\Tests;

use CodeWheel\McpToolGateway\ArrayToolProvider;
use CodeWheel\McpToolGateway\CompositeToolProvider;
use CodeWheel\McpToolGateway\ToolInfo;
use CodeWheel\McpToolGateway\ToolNotFoundException;
use CodeWheel\McpToolGateway\ToolResult;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CodeWheel\McpToolGateway\CompositeToolProvider
 */
final class CompositeToolProviderTest extends TestCase
{
    public function testGetToolsWithPrefixes(): void
    {
        $provider1 = new ArrayToolProvider([
            'tool_a' => new ToolInfo('tool_a', 'Tool A', 'First tool'),
        ]);
        $provider2 = new ArrayToolProvider([
            'tool_b' => new ToolInfo('tool_b', 'Tool B', 'Second tool'),
        ]);

        $composite = new CompositeToolProvider([
            'first' => $provider1,
            'second' => $provider2,
        ]);

        $tools = $composite->getTools();

        $this->assertCount(2, $tools);
        $this->assertArrayHasKey('first/tool_a', $tools);
        $this->assertArrayHasKey('second/tool_b', $tools);
    }

    public function testGetToolsWithoutPrefixes(): void
    {
        $provider1 = new ArrayToolProvider([
            'tool_a' => new ToolInfo('tool_a', 'Tool A', 'First tool'),
        ]);
        $provider2 = new ArrayToolProvider([
            'tool_b' => new ToolInfo('tool_b', 'Tool B', 'Second tool'),
        ]);

        $composite = new CompositeToolProvider([
            $provider1,
            $provider2,
        ], prefixed: false);

        $tools = $composite->getTools();

        $this->assertCount(2, $tools);
        $this->assertArrayHasKey('tool_a', $tools);
        $this->assertArrayHasKey('tool_b', $tools);
    }

    public function testGetTool(): void
    {
        $provider = new ArrayToolProvider([
            'my_tool' => new ToolInfo('my_tool', 'My Tool', 'A tool'),
        ]);

        $composite = new CompositeToolProvider(['ns' => $provider]);

        $tool = $composite->getTool('ns/my_tool');

        $this->assertNotNull($tool);
        $this->assertSame('ns/my_tool', $tool->name);
        $this->assertSame('my_tool', $tool->metadata['original_name']);
    }

    public function testGetToolReturnsNullForUnknown(): void
    {
        $composite = new CompositeToolProvider([]);

        $tool = $composite->getTool('unknown/tool');

        $this->assertNull($tool);
    }

    public function testExecute(): void
    {
        $provider = new ArrayToolProvider([
            'echo' => new ToolInfo('echo', 'Echo', 'Echoes input'),
        ]);
        $provider->setHandler('echo', function (array $args): ToolResult {
            return ToolResult::success('Echo: ' . ($args['message'] ?? ''));
        });

        $composite = new CompositeToolProvider(['test' => $provider]);

        $result = $composite->execute('test/echo', ['message' => 'Hello']);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Hello', $result->message);
    }

    public function testExecuteThrowsForUnknownTool(): void
    {
        $composite = new CompositeToolProvider([]);

        $this->expectException(ToolNotFoundException::class);
        $composite->execute('unknown/tool', []);
    }

    public function testAddProvider(): void
    {
        $composite = new CompositeToolProvider([]);
        $provider = new ArrayToolProvider([
            'tool' => new ToolInfo('tool', 'Tool', 'A tool'),
        ]);

        $composite->addProvider('added', $provider);

        $tools = $composite->getTools();
        $this->assertArrayHasKey('added/tool', $tools);
    }

    public function testRemoveProvider(): void
    {
        $provider = new ArrayToolProvider([
            'tool' => new ToolInfo('tool', 'Tool', 'A tool'),
        ]);

        $composite = new CompositeToolProvider(['removable' => $provider]);
        $this->assertCount(1, $composite->getTools());

        $composite->removeProvider('removable');
        $this->assertCount(0, $composite->getTools());
    }

    public function testGetProviderKeys(): void
    {
        $composite = new CompositeToolProvider([
            'alpha' => new ArrayToolProvider([]),
            'beta' => new ArrayToolProvider([]),
        ]);

        $keys = $composite->getProviderKeys();

        $this->assertSame(['alpha', 'beta'], $keys);
    }

    public function testCacheIsCleared(): void
    {
        $provider = new ArrayToolProvider([
            'tool' => new ToolInfo('tool', 'Tool', 'A tool'),
        ]);

        $composite = new CompositeToolProvider(['ns' => $provider]);

        // First call populates cache
        $tools1 = $composite->getTools();
        $this->assertCount(1, $tools1);

        // Add another provider
        $composite->addProvider('ns2', new ArrayToolProvider([
            'tool2' => new ToolInfo('tool2', 'Tool 2', 'Another'),
        ]));

        // Cache should be invalidated
        $tools2 = $composite->getTools();
        $this->assertCount(2, $tools2);
    }

    public function testMetadataContainsSourceInfo(): void
    {
        $provider = new ArrayToolProvider([
            'my_tool' => new ToolInfo(
                'my_tool',
                'My Tool',
                'A tool',
                metadata: ['custom' => 'data']
            ),
        ]);

        $composite = new CompositeToolProvider(['source' => $provider]);
        $tool = $composite->getTool('source/my_tool');

        $this->assertSame('my_tool', $tool->metadata['original_name']);
        $this->assertSame('source', $tool->metadata['source_provider']);
        $this->assertSame('data', $tool->metadata['custom']);
    }

    public function testClearCacheMethod(): void
    {
        $provider = new ArrayToolProvider([
            'tool' => new ToolInfo('tool', 'Tool', 'A tool'),
        ]);

        $composite = new CompositeToolProvider(['ns' => $provider]);

        // First call populates cache
        $composite->getTools();

        // Clear cache explicitly
        $composite->clearCache();

        // Add provider directly to internal state (simulating cache invalidation need)
        $composite->addProvider('ns2', new ArrayToolProvider([
            'tool2' => new ToolInfo('tool2', 'Tool 2', 'Another'),
        ]));

        $tools = $composite->getTools();
        $this->assertCount(2, $tools);
    }

    public function testAutoGeneratedKeysFromClassName(): void
    {
        $provider1 = new ArrayToolProvider([
            'tool_a' => new ToolInfo('tool_a', 'Tool A', 'First'),
        ]);
        $provider2 = new ArrayToolProvider([
            'tool_b' => new ToolInfo('tool_b', 'Tool B', 'Second'),
        ]);

        // Pass providers without string keys - they should get auto-generated keys
        $composite = new CompositeToolProvider([
            $provider1,
            $provider2,
        ], prefixed: true);

        $tools = $composite->getTools();
        $keys = array_keys($tools);

        // Keys should be auto-generated from class name (array, array_1)
        $this->assertCount(2, $tools);
        // Each tool should have a key that includes the auto-generated prefix
        foreach ($keys as $key) {
            $this->assertStringContainsString('/', $key);
        }
    }

    public function testProviderPropertyTracking(): void
    {
        $provider = new ArrayToolProvider([
            'tool' => new ToolInfo('tool', 'Tool', 'A tool'),
        ]);

        $composite = new CompositeToolProvider(['myns' => $provider]);
        $tool = $composite->getTool('myns/tool');

        // provider property should be set
        $this->assertSame('myns', $tool->provider);
    }

    public function testExecuteWithContext(): void
    {
        $capturedContext = null;
        $provider = new ArrayToolProvider([
            'context_tool' => new ToolInfo('context_tool', 'Context Tool', 'Uses context'),
        ]);
        $provider->setHandler('context_tool', function (array $args, ?\CodeWheel\McpToolGateway\ExecutionContext $ctx) use (&$capturedContext): ToolResult {
            $capturedContext = $ctx;
            return ToolResult::success('OK');
        });

        $composite = new CompositeToolProvider(['test' => $provider]);

        $context = \CodeWheel\McpToolGateway\ExecutionContext::create(userId: 'user-abc');
        $composite->execute('test/context_tool', [], $context);

        $this->assertNotNull($capturedContext);
        $this->assertSame('user-abc', $capturedContext->userId);
    }
}
