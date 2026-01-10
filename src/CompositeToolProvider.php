<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway;

/**
 * Combines multiple tool providers into one.
 *
 * Example with prefixes:
 * ```php
 * $provider = new CompositeToolProvider([
 *     'drupal' => $drupalProvider,    // Tools: drupal/get_users
 *     'custom' => $customProvider,     // Tools: custom/my_tool
 * ]);
 * ```
 *
 * Example without prefixes:
 * ```php
 * $provider = new CompositeToolProvider([
 *     $provider1,
 *     $provider2,
 * ], prefixed: false);
 * // Tools must have unique names across providers
 * ```
 */
final class CompositeToolProvider implements ToolProviderInterface
{
    /** @var array<string, ToolProviderInterface> */
    private array $providers;

    private bool $prefixed;

    /** @var array<string, ToolInfo>|null */
    private ?array $toolCache = null;

    /**
     * @param array<string|int, ToolProviderInterface> $providers
     *   Provider instances, optionally keyed by prefix.
     * @param bool $prefixed
     *   Whether to prefix tool names with the provider key.
     */
    public function __construct(array $providers, bool $prefixed = true)
    {
        $this->providers = [];
        $this->prefixed = $prefixed;

        $autoKeyCounter = 0;
        foreach ($providers as $key => $provider) {
            if (is_int($key)) {
                // Auto-generate unique key
                $baseKey = $this->generateKey($provider);
                $key = $autoKeyCounter > 0 ? "{$baseKey}_{$autoKeyCounter}" : $baseKey;
                $autoKeyCounter++;
            }
            $this->providers[(string) $key] = $provider;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTools(): array
    {
        if ($this->toolCache !== null) {
            return $this->toolCache;
        }

        $tools = [];

        foreach ($this->providers as $prefix => $provider) {
            foreach ($provider->getTools() as $originalName => $toolInfo) {
                $name = $this->prefixed
                    ? "{$prefix}/{$toolInfo->name}"
                    : $toolInfo->name;

                // Create new ToolInfo with prefixed name
                $tools[$name] = new ToolInfo(
                    name: $name,
                    label: $toolInfo->label,
                    description: $toolInfo->description,
                    inputSchema: $toolInfo->inputSchema,
                    annotations: $toolInfo->annotations,
                    provider: $prefix,
                    metadata: [
                        ...$toolInfo->metadata,
                        'original_name' => $toolInfo->name,
                        'source_provider' => $prefix,
                    ],
                );
            }
        }

        $this->toolCache = $tools;
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

        if ($tool === null) {
            throw new ToolNotFoundException("Tool not found: {$toolName}");
        }

        $providerKey = $tool->metadata['source_provider'] ?? null;
        $originalName = $tool->metadata['original_name'] ?? $toolName;

        if ($providerKey === null || !isset($this->providers[$providerKey])) {
            throw new ToolNotFoundException("Provider not found for tool: {$toolName}");
        }

        return $this->providers[$providerKey]->execute($originalName, $arguments, $context);
    }

    /**
     * Adds a provider to the composite.
     */
    public function addProvider(string $prefix, ToolProviderInterface $provider): self
    {
        $this->providers[$prefix] = $provider;
        $this->toolCache = null;
        return $this;
    }

    /**
     * Removes a provider from the composite.
     */
    public function removeProvider(string $prefix): self
    {
        unset($this->providers[$prefix]);
        $this->toolCache = null;
        return $this;
    }

    /**
     * Returns the provider keys.
     *
     * @return string[]
     */
    public function getProviderKeys(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Clears the tool cache.
     */
    public function clearCache(): void
    {
        $this->toolCache = null;
    }

    /**
     * Generates a key from a provider class name.
     */
    private function generateKey(ToolProviderInterface $provider): string
    {
        $class = get_class($provider);
        $parts = explode('\\', $class);
        $name = end($parts);

        // Remove common suffixes
        $name = preg_replace('/(Tool)?Provider$/', '', $name) ?? $name;

        // Convert to snake_case
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name) ?? $name);
    }
}
