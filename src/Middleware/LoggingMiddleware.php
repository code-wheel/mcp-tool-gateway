<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway\Middleware;

use CodeWheel\McpToolGateway\ExecutionContext;
use CodeWheel\McpToolGateway\ToolResult;
use Psr\Log\LoggerInterface;

/**
 * Logs tool execution start, completion, and errors.
 */
final class LoggingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly bool $logArguments = false,
        private readonly bool $logResults = false,
    ) {}

    public function process(
        string $toolName,
        array $arguments,
        ExecutionContext $context,
        callable $next,
    ): ToolResult {
        $startTime = microtime(true);
        $requestId = $context->requestId ?? 'unknown';

        $logContext = [
            'tool' => $toolName,
            'request_id' => $requestId,
        ];

        if ($this->logArguments) {
            $logContext['arguments'] = $this->sanitizeArguments($arguments);
        }

        $this->logger->info("Tool execution started: {$toolName}", $logContext);

        try {
            $result = $next($toolName, $arguments, $context);

            $duration = microtime(true) - $startTime;
            $logContext['duration_ms'] = round($duration * 1000, 2);
            $logContext['success'] = $result->success;

            if ($this->logResults && $result->success) {
                $logContext['result'] = $this->sanitizeResult($result);
            }

            if ($result->success) {
                $this->logger->info("Tool execution completed: {$toolName}", $logContext);
            } else {
                $logContext['error'] = $result->message;
                $this->logger->warning("Tool execution failed: {$toolName}", $logContext);
            }

            return $result;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;
            $logContext['duration_ms'] = round($duration * 1000, 2);
            $logContext['exception'] = get_class($e);
            $logContext['error'] = $e->getMessage();

            $this->logger->error("Tool execution exception: {$toolName}", $logContext);

            throw $e;
        }
    }

    /**
     * Sanitizes arguments for logging (removes sensitive values).
     *
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function sanitizeArguments(array $arguments): array
    {
        $sensitiveKeys = ['password', 'pass', 'secret', 'token', 'key', 'api_key', 'credential', 'auth'];
        $sanitized = [];

        foreach ($arguments as $key => $value) {
            $keyLower = strtolower((string) $key);
            $isSensitive = false;

            foreach ($sensitiveKeys as $sensitive) {
                if (str_contains($keyLower, $sensitive)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArguments($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitizes result for logging.
     *
     * @return array<string, mixed>
     */
    private function sanitizeResult(ToolResult $result): array
    {
        // Only include basic info, not full data
        return [
            'success' => $result->success,
            'message' => mb_substr($result->message, 0, 200),
            'has_data' => !empty($result->data),
        ];
    }
}
