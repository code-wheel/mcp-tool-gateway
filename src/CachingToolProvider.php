<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway;

use Psr\SimpleCache\CacheInterface;

/**
 * Wraps a provider with PSR-16 caching.
 *
 * Caches:
 * - Tool discovery (getTools, getTool)
 * - Read-only tool results (when readOnlyHint annotation is true)
 *
 * Example:
 * ```php
 * $provider = new CachingToolProvider(
 *     $innerProvider,
 *     $cache,
 *     discoveryTtl: 3600,  // Cache tool list for 1 hour
 *     resultTtl: 300,      // Cache read-only results for 5 minutes
 * );
 * ```
 */
final class CachingToolProvider implements ToolProviderInterface
{
    private const DISCOVERY_CACHE_KEY = 'mcp_tools_discovery';

    public function __construct(
        private readonly ToolProviderInterface $inner,
        private readonly CacheInterface $cache,
        private readonly int $discoveryTtl = 3600,
        private readonly int $resultTtl = 300,
        private readonly string $cachePrefix = 'mcp_tool_',
    ) {}

    /**
     * {@inheritdoc}
     */
    public function getTools(): array
    {
        $cacheKey = $this->cachePrefix . self::DISCOVERY_CACHE_KEY;

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null && is_array($cached)) {
            return $this->hydrateTools($cached);
        }

        $tools = $this->inner->getTools();

        // Store as serializable array
        $this->cache->set($cacheKey, $this->dehydrateTools($tools), $this->discoveryTtl);

        return $tools;
    }

    /**
     * {@inheritdoc}
     */
    public function getTool(string $toolName): ?ToolInfo
    {
        $tools = $this->getTools();
        return $tools[$toolName] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(
        string $toolName,
        array $arguments,
        ?ExecutionContext $context = null,
    ): ToolResult {
        $tool = $this->getTool($toolName);

        // Only cache read-only tools
        if ($tool !== null && $this->isReadOnly($tool)) {
            $cacheKey = $this->buildResultCacheKey($toolName, $arguments);
            $cached = $this->cache->get($cacheKey);

            if ($cached !== null && is_array($cached)) {
                return $this->hydrateResult($cached);
            }

            $result = $this->inner->execute($toolName, $arguments, $context);

            // Only cache successful results
            if ($result->success) {
                $this->cache->set($cacheKey, $this->dehydrateResult($result), $this->resultTtl);
            }

            return $result;
        }

        return $this->inner->execute($toolName, $arguments, $context);
    }

    /**
     * Clears the discovery cache.
     */
    public function clearDiscoveryCache(): void
    {
        $this->cache->delete($this->cachePrefix . self::DISCOVERY_CACHE_KEY);
    }

    /**
     * Clears a specific tool result from cache.
     *
     * @param array<string, mixed> $arguments
     */
    public function clearResultCache(string $toolName, array $arguments): void
    {
        $cacheKey = $this->buildResultCacheKey($toolName, $arguments);
        $this->cache->delete($cacheKey);
    }

    /**
     * Checks if a tool is read-only based on annotations.
     */
    private function isReadOnly(ToolInfo $tool): bool
    {
        return ($tool->annotations['readOnlyHint'] ?? false) === true;
    }

    /**
     * Builds a cache key for tool results.
     *
     * @param array<string, mixed> $arguments
     */
    private function buildResultCacheKey(string $toolName, array $arguments): string
    {
        $argsHash = md5(json_encode($arguments, JSON_THROW_ON_ERROR));
        return $this->cachePrefix . 'result_' . md5($toolName) . '_' . $argsHash;
    }

    /**
     * Converts ToolInfo array to cache-safe format.
     *
     * @param array<string, ToolInfo> $tools
     * @return array<string, array<string, mixed>>
     */
    private function dehydrateTools(array $tools): array
    {
        $result = [];
        foreach ($tools as $name => $tool) {
            $result[$name] = [
                'name' => $tool->name,
                'label' => $tool->label,
                'description' => $tool->description,
                'inputSchema' => $tool->inputSchema,
                'annotations' => $tool->annotations,
                'provider' => $tool->provider,
                'metadata' => $tool->metadata,
            ];
        }
        return $result;
    }

    /**
     * Converts cached data back to ToolInfo objects.
     *
     * @param array<string, array<string, mixed>> $data
     * @return array<string, ToolInfo>
     */
    private function hydrateTools(array $data): array
    {
        $tools = [];
        foreach ($data as $name => $item) {
            $tools[$name] = ToolInfo::fromArray($item);
        }
        return $tools;
    }

    /**
     * Converts ToolResult to cache-safe format.
     *
     * @return array<string, mixed>
     */
    private function dehydrateResult(ToolResult $result): array
    {
        return [
            'success' => $result->success,
            'message' => $result->message,
            'data' => $result->data,
            'isError' => $result->isError,
        ];
    }

    /**
     * Converts cached data back to ToolResult.
     *
     * @param array<string, mixed> $data
     */
    private function hydrateResult(array $data): ToolResult
    {
        return new ToolResult(
            success: $data['success'] ?? false,
            message: $data['message'] ?? '',
            data: $data['data'] ?? [],
            isError: $data['isError'] ?? false,
        );
    }
}
