<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway;

use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\ToolAnnotations;

/**
 * MCP Tool Gateway - exposes tools via 3 meta-tools: discover, info, execute.
 *
 * Instead of registering hundreds of tools with an MCP client, register these
 * 3 gateway tools. The LLM can then:
 * 1. Use discover-tools to find available tools
 * 2. Use get-tool-info to get schema for a specific tool
 * 3. Use execute-tool to run any tool dynamically
 *
 * This dramatically reduces context window usage while maintaining full capability.
 */
class ToolGateway
{
    public const DISCOVER_TOOL = 'gateway/discover-tools';
    public const GET_INFO_TOOL = 'gateway/get-tool-info';
    public const EXECUTE_TOOL = 'gateway/execute-tool';

    public function __construct(
        private readonly ToolProviderInterface $provider,
        private readonly ?string $toolPrefix = null,
    ) {}

    /**
     * Returns the 3 gateway tool definitions for MCP server registration.
     *
     * @return array<int, array{
     *   name: string,
     *   description: string,
     *   handler: callable,
     *   inputSchema: array<string, mixed>,
     *   annotations: ToolAnnotations,
     * }>
     */
    public function getGatewayTools(): array
    {
        $prefix = $this->toolPrefix ? "{$this->toolPrefix}/" : '';

        return [
            [
                'name' => $prefix . self::DISCOVER_TOOL,
                'description' => 'List available tools with optional filtering. Returns tool names, labels, descriptions, and hints.',
                'handler' => fn(?string $query = null): CallToolResult => $this->discoverTools($query),
                'inputSchema' => $this->buildSchema([
                    'query' => [
                        'type' => 'string',
                        'description' => 'Optional search term to filter tools by name, label, or description.',
                    ],
                ]),
                'annotations' => ToolAnnotations::fromArray([
                    'title' => 'Discover Tools',
                    'readOnlyHint' => true,
                    'idempotentHint' => true,
                    'openWorldHint' => false,
                ]),
            ],
            [
                'name' => $prefix . self::GET_INFO_TOOL,
                'description' => 'Get detailed information about a specific tool including its input schema and annotations.',
                'handler' => fn(string $tool_name): CallToolResult => $this->getToolInfo($tool_name),
                'inputSchema' => $this->buildSchema([
                    'tool_name' => [
                        'type' => 'string',
                        'description' => 'The tool name from discover-tools results.',
                    ],
                ], ['tool_name']),
                'annotations' => ToolAnnotations::fromArray([
                    'title' => 'Get Tool Info',
                    'readOnlyHint' => true,
                    'idempotentHint' => true,
                    'openWorldHint' => false,
                ]),
            ],
            [
                'name' => $prefix . self::EXECUTE_TOOL,
                'description' => 'Execute any available tool by name with the provided arguments.',
                'handler' => fn(string $tool_name, ?array $arguments = null, ?ExecutionContext $context = null): CallToolResult
                    => $this->executeTool($tool_name, $arguments ?? [], $context),
                'inputSchema' => $this->buildSchema([
                    'tool_name' => [
                        'type' => 'string',
                        'description' => 'The tool name from discover-tools results.',
                    ],
                    'arguments' => [
                        'type' => 'object',
                        'description' => 'Arguments to pass to the tool (see get-tool-info for schema).',
                    ],
                ], ['tool_name']),
                'annotations' => ToolAnnotations::fromArray([
                    'title' => 'Execute Tool',
                    'readOnlyHint' => false,
                    'openWorldHint' => true,
                ]),
            ],
        ];
    }

    /**
     * Discovers available tools with optional filtering.
     */
    public function discoverTools(?string $query = null): CallToolResult
    {
        $tools = $this->provider->getTools();
        $results = [];
        $query = $query !== null ? strtolower(trim($query)) : null;

        foreach ($tools as $toolInfo) {
            if ($query !== null && $query !== '') {
                $haystack = strtolower("{$toolInfo->name} {$toolInfo->label} {$toolInfo->description}");
                if (!str_contains($haystack, $query)) {
                    continue;
                }
            }

            $results[] = $toolInfo->toDiscoverySummary();
        }

        $structured = [
            'success' => true,
            'count' => count($results),
            'tools' => $results,
        ];

        $text = "Found " . count($results) . " tools.";
        if (!empty($results)) {
            $text .= "\n" . json_encode($structured, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return new CallToolResult([new TextContent($text)], false, $structured);
    }

    /**
     * Returns detailed information about a specific tool.
     */
    public function getToolInfo(string $toolName): CallToolResult
    {
        $tool = $this->provider->getTool($toolName);

        if ($tool === null) {
            return $this->errorResult("Unknown tool: {$toolName}");
        }

        $structured = [
            'success' => true,
            ...$tool->toDetailedInfo(),
        ];

        $content = [new TextContent(json_encode($structured, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))];

        return new CallToolResult($content, false, $structured);
    }

    /**
     * Executes a tool via the gateway.
     *
     * @param array<string, mixed> $arguments
     */
    public function executeTool(string $toolName, array $arguments, ?ExecutionContext $context = null): CallToolResult
    {
        try {
            $result = $this->provider->execute($toolName, $arguments, $context);

            $structured = $result->toArray();
            $content = [new TextContent($result->message)];

            return new CallToolResult($content, $result->isError, $structured);
        } catch (ToolNotFoundException $e) {
            return $this->errorResult("Unknown tool: {$toolName}");
        } catch (ToolExecutionException $e) {
            return $this->errorResult($e->getMessage(), ['tool' => $toolName, ...$e->context]);
        } catch (\Throwable $e) {
            return $this->errorResult("Tool execution failed: {$e->getMessage()}", ['tool' => $toolName]);
        }
    }

    /**
     * Creates an error result.
     *
     * @param array<string, mixed> $structured
     */
    private function errorResult(string $message, array $structured = []): CallToolResult
    {
        $payload = ['success' => false, 'error' => $message, ...$structured];
        $content = [new TextContent($message)];

        return new CallToolResult($content, true, $payload);
    }

    /**
     * Builds a JSON Schema for tool input.
     *
     * @param array<string, mixed> $properties
     * @param string[] $required
     * @return array<string, mixed>
     */
    private function buildSchema(array $properties, array $required = []): array
    {
        $schema = [
            'type' => 'object',
            'properties' => !empty($properties) ? $properties : new \stdClass(),
        ];

        if (!empty($required)) {
            $schema['required'] = $required;
        }

        return $schema;
    }
}
