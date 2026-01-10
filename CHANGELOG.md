# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2025-01-09

### Added
- **Middleware Pipeline**
  - `MiddlewareInterface` for implementing custom middleware
  - `MiddlewarePipeline` for chaining middleware with before/after hooks
  - `add()`, `addMany()`, `clear()`, `count()` methods
- **Built-in Middleware**
  - `ValidatingMiddleware` - Validates inputs against JSON Schema (integrates with mcp-schema-builder)
  - `LoggingMiddleware` - PSR-3 logging with argument sanitization
  - `EventMiddleware` - PSR-14 event dispatching for tool lifecycle
- **Tool Composition**
  - `CompositeToolProvider` - Combine multiple providers with optional namespacing
  - `addProvider()`, `removeProvider()`, `getProviderKeys()` for dynamic management
- **Caching**
  - `CachingToolProvider` - PSR-16 cache for tool discovery and read-only results
  - Configurable TTL for discovery and execution caching
  - Selective caching by tool name
- **Events**
  - `ToolExecutionStarted` - Dispatched before tool execution
  - `ToolExecutionSucceeded` - Dispatched after successful execution (includes duration)
  - `ToolExecutionFailed` - Dispatched on failure (includes exception)
- **ExecutionContext Enhancements**
  - `requestId` for request tracing
  - `scopes` array for authorization
  - `hasScope()`, `hasAnyScope()` helper methods
  - `create()` factory method

### Changed
- Updated CI to test PHP 8.1-8.4
- Added integration tests with mcp-schema-builder

## [1.0.0] - 2025-01-07

### Added
- Initial release with gateway pattern implementation
- `ToolProviderInterface` for framework integration
- `ArrayToolProvider` for simple tool registration
- `ToolGateway` with discover/get-info/execute meta-tools
- `ToolInfo` and `ToolResult` DTOs
- `ExecutionContext` for request metadata
- `ToolNotFoundException` and `ToolExecutionException`
