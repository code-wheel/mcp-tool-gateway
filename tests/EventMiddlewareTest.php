<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway\Tests;

use CodeWheel\McpToolGateway\Event\ToolExecutionFailed;
use CodeWheel\McpToolGateway\Event\ToolExecutionStarted;
use CodeWheel\McpToolGateway\Event\ToolExecutionSucceeded;
use CodeWheel\McpToolGateway\ExecutionContext;
use CodeWheel\McpToolGateway\Middleware\EventMiddleware;
use CodeWheel\McpToolGateway\ToolResult;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \CodeWheel\McpToolGateway\Middleware\EventMiddleware
 */
final class EventMiddlewareTest extends TestCase
{
    private EventDispatcherInterface $dispatcher;
    private ExecutionContext $context;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->context = ExecutionContext::create(requestId: 'req-456', userId: 'user-1');
    }

    public function testDispatchesStartedAndSucceededEvents(): void
    {
        $dispatchedEvents = [];

        $this->dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;
                return $event;
            });

        $middleware = new EventMiddleware($this->dispatcher);
        $next = fn() => ToolResult::success('OK', ['key' => 'value']);

        $result = $middleware->process('my_tool', ['arg1' => 'val1'], $this->context, $next);

        $this->assertTrue($result->success);
        $this->assertCount(2, $dispatchedEvents);

        // First event: Started
        $this->assertInstanceOf(ToolExecutionStarted::class, $dispatchedEvents[0]);
        $this->assertSame('my_tool', $dispatchedEvents[0]->toolName);
        $this->assertSame(['arg1' => 'val1'], $dispatchedEvents[0]->arguments);
        $this->assertSame($this->context, $dispatchedEvents[0]->context);

        // Second event: Succeeded
        $this->assertInstanceOf(ToolExecutionSucceeded::class, $dispatchedEvents[1]);
        $this->assertSame('my_tool', $dispatchedEvents[1]->toolName);
        $this->assertSame(['arg1' => 'val1'], $dispatchedEvents[1]->arguments);
        $this->assertSame($result, $dispatchedEvents[1]->result);
        $this->assertSame($this->context, $dispatchedEvents[1]->context);
    }

    public function testDispatchesStartedAndFailedEventsForToolError(): void
    {
        $dispatchedEvents = [];

        $this->dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;
                return $event;
            });

        $middleware = new EventMiddleware($this->dispatcher);
        $next = fn() => ToolResult::error('Something went wrong');

        $result = $middleware->process('failing_tool', [], $this->context, $next);

        $this->assertFalse($result->success);
        $this->assertCount(2, $dispatchedEvents);

        // First event: Started
        $this->assertInstanceOf(ToolExecutionStarted::class, $dispatchedEvents[0]);

        // Second event: Failed (without exception)
        $this->assertInstanceOf(ToolExecutionFailed::class, $dispatchedEvents[1]);
        $this->assertSame('failing_tool', $dispatchedEvents[1]->toolName);
        $this->assertSame('Something went wrong', $dispatchedEvents[1]->error);
        $this->assertNull($dispatchedEvents[1]->exception);
    }

    public function testDispatchesStartedAndFailedEventsForException(): void
    {
        $dispatchedEvents = [];

        $this->dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;
                return $event;
            });

        $middleware = new EventMiddleware($this->dispatcher);
        $exception = new \RuntimeException('Boom!');
        $next = function () use ($exception) {
            throw $exception;
        };

        try {
            $middleware->process('crashing_tool', ['x' => 'y'], $this->context, $next);
            $this->fail('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('Boom!', $e->getMessage());
        }

        $this->assertCount(2, $dispatchedEvents);

        // First event: Started
        $this->assertInstanceOf(ToolExecutionStarted::class, $dispatchedEvents[0]);
        $this->assertSame('crashing_tool', $dispatchedEvents[0]->toolName);

        // Second event: Failed (with exception)
        $this->assertInstanceOf(ToolExecutionFailed::class, $dispatchedEvents[1]);
        $this->assertSame('crashing_tool', $dispatchedEvents[1]->toolName);
        $this->assertSame('Boom!', $dispatchedEvents[1]->error);
        $this->assertSame($exception, $dispatchedEvents[1]->exception);
    }

    public function testEventTimestamps(): void
    {
        $startEvent = null;
        $endEvent = null;

        $this->dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$startEvent, &$endEvent) {
                if ($event instanceof ToolExecutionStarted) {
                    $startEvent = $event;
                } elseif ($event instanceof ToolExecutionSucceeded) {
                    $endEvent = $event;
                }
                return $event;
            });

        $middleware = new EventMiddleware($this->dispatcher);
        $next = function () {
            usleep(1000); // 1ms delay
            return ToolResult::success('OK');
        };

        $middleware->process('timed_tool', [], $this->context, $next);

        $this->assertNotNull($startEvent);
        $this->assertNotNull($endEvent);

        // Timestamps should be set
        $this->assertGreaterThan(0, $startEvent->timestamp);
        $this->assertGreaterThan(0, $endEvent->startTime);
        $this->assertGreaterThan(0, $endEvent->endTime);

        // End should be after start
        $this->assertGreaterThanOrEqual($endEvent->startTime, $endEvent->endTime);
        $this->assertSame($startEvent->timestamp, $endEvent->startTime);
    }

    public function testFailedEventTimestampsForException(): void
    {
        $failedEvent = null;

        $this->dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$failedEvent) {
                if ($event instanceof ToolExecutionFailed) {
                    $failedEvent = $event;
                }
                return $event;
            });

        $middleware = new EventMiddleware($this->dispatcher);
        $next = function () {
            throw new \Exception('Error');
        };

        try {
            $middleware->process('error_tool', [], $this->context, $next);
        } catch (\Exception $e) {
            // Expected
        }

        $this->assertNotNull($failedEvent);
        $this->assertGreaterThan(0, $failedEvent->startTime);
        $this->assertGreaterThan(0, $failedEvent->endTime);
    }
}
