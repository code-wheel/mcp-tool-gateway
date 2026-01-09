<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway;

/**
 * Interface for backends that provide tools to the gateway.
 *
 * Implement this interface to expose your system's tools through the MCP gateway.
 * The gateway will use this to discover, describe, and execute tools.
 */
interface ToolProviderInterface
{
    /**
     * Returns all available tools.
     *
     * @return array<string, ToolInfo> Tools keyed by unique tool name.
     */
    public function getTools(): array;

    /**
     * Returns detailed information about a specific tool.
     *
     * @param string $toolName The tool name/identifier.
     * @return ToolInfo|null Tool info or null if not found.
     */
    public function getTool(string $toolName): ?ToolInfo;

    /**
     * Executes a tool with the given arguments.
     *
     * @param string $toolName The tool name/identifier.
     * @param array<string, mixed> $arguments Arguments to pass to the tool.
     * @param ExecutionContext|null $context Optional execution context.
     * @return ToolResult The execution result.
     * @throws ToolNotFoundException If the tool doesn't exist.
     * @throws ToolExecutionException If execution fails.
     */
    public function execute(string $toolName, array $arguments, ?ExecutionContext $context = null): ToolResult;
}
