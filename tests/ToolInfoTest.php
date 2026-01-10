<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway\Tests;

use CodeWheel\McpToolGateway\ToolInfo;
use PHPUnit\Framework\TestCase;

class ToolInfoTest extends TestCase
{
    public function testConstruction(): void
    {
        $tool = new ToolInfo(
            name: 'test-tool',
            label: 'Test Tool',
            description: 'A test tool',
            inputSchema: ['type' => 'object'],
            annotations: ['readOnlyHint' => true],
            provider: 'test-provider',
        );

        $this->assertSame('test-tool', $tool->name);
        $this->assertSame('Test Tool', $tool->label);
        $this->assertSame('A test tool', $tool->description);
        $this->assertSame(['type' => 'object'], $tool->inputSchema);
        $this->assertSame(['readOnlyHint' => true], $tool->annotations);
        $this->assertSame('test-provider', $tool->provider);
    }

    public function testToDiscoverySummary(): void
    {
        $tool = new ToolInfo(
            name: 'my-tool',
            label: 'My Tool',
            description: 'Does something',
            annotations: [
                'readOnlyHint' => true,
                'destructiveHint' => false,
                'idempotentHint' => true,
            ],
            provider: 'my-module',
        );

        $summary = $tool->toDiscoverySummary();

        $this->assertSame('my-tool', $summary['name']);
        $this->assertSame('My Tool', $summary['label']);
        $this->assertSame('Does something', $summary['description']);
        $this->assertSame('my-module', $summary['provider']);
        $this->assertSame([
            'read_only' => true,
            'destructive' => false,
            'idempotent' => true,
        ], $summary['hints']);
    }

    public function testToDiscoverySummaryFiltersNullHints(): void
    {
        $tool = new ToolInfo(
            name: 'tool',
            label: 'Tool',
            description: 'Desc',
            annotations: [
                'readOnlyHint' => true,
                'destructiveHint' => null,
            ],
        );

        $summary = $tool->toDiscoverySummary();

        $this->assertSame(['read_only' => true], $summary['hints']);
    }

    public function testToDetailedInfo(): void
    {
        $tool = new ToolInfo(
            name: 'detailed-tool',
            label: 'Detailed Tool',
            description: 'Full description',
            inputSchema: ['type' => 'object', 'properties' => ['foo' => ['type' => 'string']]],
            annotations: ['readOnlyHint' => false],
            provider: 'provider',
            metadata: ['version' => '1.0'],
        );

        $info = $tool->toDetailedInfo();

        $this->assertSame('detailed-tool', $info['name']);
        $this->assertSame('Detailed Tool', $info['label']);
        $this->assertSame('Full description', $info['description']);
        $this->assertSame(['type' => 'object', 'properties' => ['foo' => ['type' => 'string']]], $info['input_schema']);
        $this->assertSame(['readOnlyHint' => false], $info['annotations']);
        $this->assertSame('provider', $info['provider']);
        $this->assertSame(['version' => '1.0'], $info['metadata']);
    }

    public function testFromArray(): void
    {
        $data = [
            'name' => 'from-array-tool',
            'label' => 'From Array',
            'description' => 'Created from array',
            'input_schema' => ['type' => 'object'],
            'annotations' => ['readOnlyHint' => true],
            'provider' => 'array-provider',
            'metadata' => ['key' => 'value'],
        ];

        $tool = ToolInfo::fromArray($data);

        $this->assertSame('from-array-tool', $tool->name);
        $this->assertSame('From Array', $tool->label);
        $this->assertSame('Created from array', $tool->description);
        $this->assertSame(['type' => 'object'], $tool->inputSchema);
        $this->assertSame(['readOnlyHint' => true], $tool->annotations);
        $this->assertSame('array-provider', $tool->provider);
        $this->assertSame(['key' => 'value'], $tool->metadata);
    }

    public function testFromArrayWithInputSchemaKey(): void
    {
        $data = [
            'name' => 'tool',
            'inputSchema' => ['type' => 'object'],
        ];

        $tool = ToolInfo::fromArray($data);

        $this->assertSame(['type' => 'object'], $tool->inputSchema);
    }

    public function testFromArrayWithMinimalData(): void
    {
        // Test all the default fallbacks
        $tool = ToolInfo::fromArray([]);

        $this->assertSame('', $tool->name);
        $this->assertSame('', $tool->label); // Falls back to name, which is also empty
        $this->assertSame('', $tool->description);
        $this->assertSame([], $tool->inputSchema);
        $this->assertSame([], $tool->annotations);
        $this->assertNull($tool->provider);
        $this->assertSame([], $tool->metadata);
    }

    public function testFromArrayLabelFallsBackToName(): void
    {
        $tool = ToolInfo::fromArray([
            'name' => 'my_tool',
            // No label provided
        ]);

        $this->assertSame('my_tool', $tool->label);
    }

    public function testToDiscoverySummaryWithNoHints(): void
    {
        $tool = new ToolInfo(
            name: 'simple',
            label: 'Simple',
            description: 'A simple tool',
            // No annotations
        );

        $summary = $tool->toDiscoverySummary();

        $this->assertSame([], $summary['hints']);
        $this->assertNull($summary['provider']);
    }

    public function testConstructorDefaults(): void
    {
        $tool = new ToolInfo(
            name: 'minimal',
            label: 'Minimal',
            description: 'Minimal tool',
        );

        $this->assertSame([], $tool->inputSchema);
        $this->assertSame([], $tool->annotations);
        $this->assertNull($tool->provider);
        $this->assertSame([], $tool->metadata);
    }
}
