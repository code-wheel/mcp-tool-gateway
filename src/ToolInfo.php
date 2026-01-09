<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway;

/**
 * Represents metadata about a tool.
 */
final class ToolInfo
{
    /**
     * @param string $name Unique tool identifier.
     * @param string $label Human-readable label.
     * @param string $description Description of what the tool does.
     * @param array<string, mixed> $inputSchema JSON Schema for input validation.
     * @param array<string, mixed> $annotations MCP tool annotations (hints).
     * @param string|null $provider Provider/module that owns this tool.
     * @param array<string, mixed> $metadata Additional metadata.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $description,
        public readonly array $inputSchema = [],
        public readonly array $annotations = [],
        public readonly ?string $provider = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * Creates a lightweight summary for discovery results.
     *
     * @return array<string, mixed>
     */
    public function toDiscoverySummary(): array
    {
        $hints = array_filter([
            'read_only' => $this->annotations['readOnlyHint'] ?? null,
            'destructive' => $this->annotations['destructiveHint'] ?? null,
            'idempotent' => $this->annotations['idempotentHint'] ?? null,
        ], static fn($value): bool => $value !== null);

        return [
            'name' => $this->name,
            'label' => $this->label,
            'description' => $this->description,
            'provider' => $this->provider,
            'hints' => $hints,
        ];
    }

    /**
     * Creates a detailed info response.
     *
     * @return array<string, mixed>
     */
    public function toDetailedInfo(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'description' => $this->description,
            'provider' => $this->provider,
            'input_schema' => $this->inputSchema,
            'annotations' => $this->annotations,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Creates ToolInfo from an array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            label: $data['label'] ?? $data['name'] ?? '',
            description: $data['description'] ?? '',
            inputSchema: $data['input_schema'] ?? $data['inputSchema'] ?? [],
            annotations: $data['annotations'] ?? [],
            provider: $data['provider'] ?? null,
            metadata: $data['metadata'] ?? [],
        );
    }
}
