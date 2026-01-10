<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway\Tests;

use CodeWheel\McpToolGateway\ExecutionContext;
use CodeWheel\McpToolGateway\Middleware\LoggingMiddleware;
use CodeWheel\McpToolGateway\ToolResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \CodeWheel\McpToolGateway\Middleware\LoggingMiddleware
 */
final class LoggingMiddlewareTest extends TestCase
{
    private LoggerInterface $logger;
    private ExecutionContext $context;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->context = new ExecutionContext(requestId: 'req-123');
    }

    public function testLogsSuccessfulExecution(): void
    {
        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) {
                static $call = 0;
                $call++;

                if ($call === 1) {
                    $this->assertStringContainsString('started', $message);
                    $this->assertSame('test_tool', $context['tool']);
                    $this->assertSame('req-123', $context['request_id']);
                } elseif ($call === 2) {
                    $this->assertStringContainsString('completed', $message);
                    $this->assertTrue($context['success']);
                    $this->assertArrayHasKey('duration_ms', $context);
                }
            });

        $middleware = new LoggingMiddleware($this->logger);
        $next = fn() => ToolResult::success('OK');

        $result = $middleware->process('test_tool', [], $this->context, $next);

        $this->assertTrue($result->success);
    }

    public function testLogsFailedExecution(): void
    {
        $infoCalled = false;
        $warningCalled = false;

        $this->logger->expects($this->once())
            ->method('info')
            ->willReturnCallback(function () use (&$infoCalled) {
                $infoCalled = true;
            });

        $this->logger->expects($this->once())
            ->method('warning')
            ->willReturnCallback(function (string $message, array $context) use (&$warningCalled) {
                $warningCalled = true;
                $this->assertStringContainsString('failed', $message);
                $this->assertFalse($context['success']);
                $this->assertSame('Something went wrong', $context['error']);
            });

        $middleware = new LoggingMiddleware($this->logger);
        $next = fn() => ToolResult::error('Something went wrong');

        $result = $middleware->process('test_tool', [], $this->context, $next);

        $this->assertFalse($result->success);
        $this->assertTrue($infoCalled);
        $this->assertTrue($warningCalled);
    }

    public function testLogsExceptionAndRethrows(): void
    {
        $this->logger->expects($this->once())->method('info');
        $this->logger->expects($this->once())
            ->method('error')
            ->willReturnCallback(function (string $message, array $context) {
                $this->assertStringContainsString('exception', $message);
                $this->assertSame('RuntimeException', $context['exception']);
                $this->assertSame('Boom!', $context['error']);
                $this->assertArrayHasKey('duration_ms', $context);
            });

        $middleware = new LoggingMiddleware($this->logger);
        $next = function () {
            throw new \RuntimeException('Boom!');
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Boom!');

        $middleware->process('test_tool', [], $this->context, $next);
    }

    public function testLogsArgumentsWhenEnabled(): void
    {
        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) {
                static $call = 0;
                $call++;

                if ($call === 1) {
                    $this->assertArrayHasKey('arguments', $context);
                    $this->assertSame('value', $context['arguments']['name']);
                }
            });

        $middleware = new LoggingMiddleware($this->logger, logArguments: true);
        $next = fn() => ToolResult::success('OK');

        $middleware->process('test_tool', ['name' => 'value'], $this->context, $next);
    }

    public function testSanitizesSensitiveArguments(): void
    {
        $capturedArgs = null;

        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedArgs) {
                if (isset($context['arguments'])) {
                    $capturedArgs = $context['arguments'];
                }
            });

        $middleware = new LoggingMiddleware($this->logger, logArguments: true);
        $next = fn() => ToolResult::success('OK');

        $middleware->process('test_tool', [
            'username' => 'john',
            'password' => 'secret123',
            'api_key' => 'key-abc',
            'token' => 'tok-xyz',
            'auth_header' => 'Bearer xxx',
            'secret_data' => 'hidden',
            'credential_file' => '/path/to/cred',
        ], $this->context, $next);

        $this->assertSame('john', $capturedArgs['username']);
        $this->assertSame('[REDACTED]', $capturedArgs['password']);
        $this->assertSame('[REDACTED]', $capturedArgs['api_key']);
        $this->assertSame('[REDACTED]', $capturedArgs['token']);
        $this->assertSame('[REDACTED]', $capturedArgs['auth_header']);
        $this->assertSame('[REDACTED]', $capturedArgs['secret_data']);
        $this->assertSame('[REDACTED]', $capturedArgs['credential_file']);
    }

    public function testSanitizesNestedSensitiveArguments(): void
    {
        $capturedArgs = null;

        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedArgs) {
                if (isset($context['arguments'])) {
                    $capturedArgs = $context['arguments'];
                }
            });

        $middleware = new LoggingMiddleware($this->logger, logArguments: true);
        $next = fn() => ToolResult::success('OK');

        $middleware->process('test_tool', [
            'config' => [
                'user' => 'admin',
                'password' => 'admin123',
            ],
        ], $this->context, $next);

        $this->assertSame('admin', $capturedArgs['config']['user']);
        $this->assertSame('[REDACTED]', $capturedArgs['config']['password']);
    }

    public function testLogsResultsWhenEnabled(): void
    {
        $capturedContext = null;

        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedContext) {
                static $call = 0;
                $call++;

                if ($call === 2) {
                    $capturedContext = $context;
                }
            });

        $middleware = new LoggingMiddleware($this->logger, logResults: true);
        $next = fn() => ToolResult::success('This is a long success message with lots of details', ['data' => 'value']);

        $middleware->process('test_tool', [], $this->context, $next);

        $this->assertArrayHasKey('result', $capturedContext);
        $this->assertTrue($capturedContext['result']['success']);
        $this->assertTrue($capturedContext['result']['has_data']);
        $this->assertLessThanOrEqual(200, strlen($capturedContext['result']['message']));
    }

    public function testDoesNotLogResultsForFailedExecution(): void
    {
        $warningContext = null;

        $this->logger->expects($this->once())->method('info');
        $this->logger->expects($this->once())
            ->method('warning')
            ->willReturnCallback(function (string $message, array $context) use (&$warningContext) {
                $warningContext = $context;
            });

        $middleware = new LoggingMiddleware($this->logger, logResults: true);
        $next = fn() => ToolResult::error('Failed');

        $middleware->process('test_tool', [], $this->context, $next);

        // Should have error message but not result array
        $this->assertArrayNotHasKey('result', $warningContext);
        $this->assertSame('Failed', $warningContext['error']);
    }

    public function testUsesUnknownForMissingRequestId(): void
    {
        $capturedContext = null;

        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedContext) {
                $capturedContext = $context;
            });

        $middleware = new LoggingMiddleware($this->logger);
        $context = new ExecutionContext(); // No requestId
        $next = fn() => ToolResult::success('OK');

        $middleware->process('test_tool', [], $context, $next);

        $this->assertSame('unknown', $capturedContext['request_id']);
    }
}
