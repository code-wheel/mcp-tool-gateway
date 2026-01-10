<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway\Tests;

use CodeWheel\McpToolGateway\ArrayToolProvider;
use CodeWheel\McpToolGateway\CachingToolProvider;
use CodeWheel\McpToolGateway\ExecutionContext;
use CodeWheel\McpToolGateway\ToolInfo;
use CodeWheel\McpToolGateway\ToolResult;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

/**
 * @covers \CodeWheel\McpToolGateway\CachingToolProvider
 */
final class CachingToolProviderTest extends TestCase
{
    private ArrayToolProvider $inner;
    private CacheInterface $cache;

    protected function setUp(): void
    {
        $this->inner = new ArrayToolProvider([
            'read_tool' => new ToolInfo(
                name: 'read_tool',
                label: 'Read Tool',
                description: 'A read-only tool',
                annotations: ['readOnlyHint' => true],
            ),
            'write_tool' => new ToolInfo(
                name: 'write_tool',
                label: 'Write Tool',
                description: 'A write tool',
                annotations: ['readOnlyHint' => false],
            ),
        ]);

        $this->inner->setHandler('read_tool', fn(array $args) => ToolResult::success('Read: ' . json_encode($args), $args));
        $this->inner->setHandler('write_tool', fn(array $args) => ToolResult::success('Written: ' . json_encode($args), $args));

        $this->cache = $this->createMock(CacheInterface::class);
    }

    public function testGetToolsFromCache(): void
    {
        $cachedData = [
            'read_tool' => [
                'name' => 'read_tool',
                'label' => 'Read Tool',
                'description' => 'A read-only tool',
                'inputSchema' => [],
                'annotations' => ['readOnlyHint' => true],
                'provider' => null,
                'metadata' => [],
            ],
        ];

        $this->cache->expects($this->once())
            ->method('get')
            ->with('mcp_tool_mcp_tools_discovery')
            ->willReturn($cachedData);

        $provider = new CachingToolProvider($this->inner, $this->cache);
        $tools = $provider->getTools();

        $this->assertCount(1, $tools);
        $this->assertArrayHasKey('read_tool', $tools);
        $this->assertSame('read_tool', $tools['read_tool']->name);
    }

    public function testGetToolsFetchesWhenCacheMiss(): void
    {
        $this->cache->expects($this->once())
            ->method('get')
            ->with('mcp_tool_mcp_tools_discovery')
            ->willReturn(null);

        $this->cache->expects($this->once())
            ->method('set')
            ->with(
                'mcp_tool_mcp_tools_discovery',
                $this->isType('array'),
                3600
            );

        $provider = new CachingToolProvider($this->inner, $this->cache);
        $tools = $provider->getTools();

        $this->assertCount(2, $tools);
        $this->assertArrayHasKey('read_tool', $tools);
        $this->assertArrayHasKey('write_tool', $tools);
    }

    public function testGetTool(): void
    {
        $this->cache->method('get')->willReturn(null);
        $this->cache->method('set');

        $provider = new CachingToolProvider($this->inner, $this->cache);

        $tool = $provider->getTool('read_tool');
        $this->assertNotNull($tool);
        $this->assertSame('read_tool', $tool->name);

        $notFound = $provider->getTool('nonexistent');
        $this->assertNull($notFound);
    }

    public function testExecuteReadOnlyToolWithCacheHit(): void
    {
        $cachedResult = [
            'success' => true,
            'message' => 'Cached result',
            'data' => ['cached' => true],
            'isError' => false,
        ];

        $this->cache->method('get')
            ->willReturnCallback(function (string $key) use ($cachedResult) {
                if (str_contains($key, 'discovery')) {
                    return null;
                }
                if (str_contains($key, 'result_')) {
                    return $cachedResult;
                }
                return null;
            });

        $this->cache->method('set');

        $provider = new CachingToolProvider($this->inner, $this->cache);
        $result = $provider->execute('read_tool', ['foo' => 'bar']);

        $this->assertTrue($result->success);
        $this->assertSame('Cached result', $result->message);
        $this->assertTrue($result->data['cached']);
    }

    public function testExecuteReadOnlyToolCachesMissResult(): void
    {
        $setCalls = [];
        $this->cache->method('get')->willReturn(null);
        $this->cache->method('set')
            ->willReturnCallback(function (string $key, $value, $ttl) use (&$setCalls) {
                $setCalls[] = ['key' => $key, 'value' => $value, 'ttl' => $ttl];
            });

        $provider = new CachingToolProvider($this->inner, $this->cache, resultTtl: 600);
        $result = $provider->execute('read_tool', ['foo' => 'bar']);

        $this->assertTrue($result->success);

        // Should have set both discovery cache and result cache
        $resultCacheCall = array_filter($setCalls, fn($c) => str_contains($c['key'], 'result_'));
        $this->assertCount(1, $resultCacheCall);
    }

    public function testExecuteWriteToolSkipsCache(): void
    {
        $getCalls = [];
        $this->cache->method('get')
            ->willReturnCallback(function (string $key) use (&$getCalls) {
                $getCalls[] = $key;
                return null;
            });

        $this->cache->method('set');

        $provider = new CachingToolProvider($this->inner, $this->cache);
        $result = $provider->execute('write_tool', ['data' => 'value']);

        $this->assertTrue($result->success);

        // Should only have discovery cache call, not result cache
        $resultCacheCalls = array_filter($getCalls, fn($k) => str_contains($k, 'result_'));
        $this->assertEmpty($resultCacheCalls);
    }

    public function testExecuteDoesNotCacheFailedResults(): void
    {
        $this->inner->setHandler('read_tool', fn() => ToolResult::error('Failed'));

        $setCalls = [];
        $this->cache->method('get')->willReturn(null);
        $this->cache->method('set')
            ->willReturnCallback(function (string $key) use (&$setCalls) {
                $setCalls[] = $key;
            });

        $provider = new CachingToolProvider($this->inner, $this->cache);
        $result = $provider->execute('read_tool', []);

        $this->assertFalse($result->success);

        // Should only have discovery cache, not result cache
        $resultCacheCalls = array_filter($setCalls, fn($k) => str_contains($k, 'result_'));
        $this->assertEmpty($resultCacheCalls);
    }

    public function testClearDiscoveryCache(): void
    {
        $this->cache->expects($this->once())
            ->method('delete')
            ->with('mcp_tool_mcp_tools_discovery');

        $provider = new CachingToolProvider($this->inner, $this->cache);
        $provider->clearDiscoveryCache();
    }

    public function testClearResultCache(): void
    {
        $this->cache->expects($this->once())
            ->method('delete')
            ->with($this->stringContains('mcp_tool_result_'));

        $provider = new CachingToolProvider($this->inner, $this->cache);
        $provider->clearResultCache('read_tool', ['foo' => 'bar']);
    }

    public function testCustomCachePrefix(): void
    {
        $this->cache->expects($this->once())
            ->method('get')
            ->with('custom_mcp_tools_discovery')
            ->willReturn(null);

        $this->cache->method('set');

        $provider = new CachingToolProvider(
            $this->inner,
            $this->cache,
            cachePrefix: 'custom_'
        );

        $provider->getTools();
    }

    public function testCustomTtlValues(): void
    {
        $this->cache->method('get')->willReturn(null);

        $setCalls = [];
        $this->cache->method('set')
            ->willReturnCallback(function (string $key, $value, $ttl) use (&$setCalls) {
                $setCalls[$key] = $ttl;
            });

        $provider = new CachingToolProvider(
            $this->inner,
            $this->cache,
            discoveryTtl: 7200,
            resultTtl: 1800,
        );

        $provider->getTools();
        $provider->execute('read_tool', []);

        $this->assertSame(7200, $setCalls['mcp_tool_mcp_tools_discovery']);
    }

    public function testExecuteWithContext(): void
    {
        $this->cache->method('get')->willReturn(null);
        $this->cache->method('set');

        $provider = new CachingToolProvider($this->inner, $this->cache);
        $context = ExecutionContext::create(userId: 'user-123');

        $result = $provider->execute('read_tool', ['test' => 'value'], $context);

        $this->assertTrue($result->success);
    }
}
