<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway\Tests;

use CodeWheel\McpToolGateway\Event\ToolExecutionFailed;
use CodeWheel\McpToolGateway\Event\ToolExecutionStarted;
use CodeWheel\McpToolGateway\Event\ToolExecutionSucceeded;
use CodeWheel\McpToolGateway\ExecutionContext;
use CodeWheel\McpToolGateway\ToolResult;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CodeWheel\McpToolGateway\Event\ToolExecutionStarted
 * @covers \CodeWheel\McpToolGateway\Event\ToolExecutionSucceeded
 * @covers \CodeWheel\McpToolGateway\Event\ToolExecutionFailed
 */
final class EventTest extends TestCase
{
    private ExecutionContext $context;

    protected function setUp(): void
    {
        $this->context = new ExecutionContext(requestId: 'req-789', userId: 'user-abc');
    }

    // =========================================================================
    // ToolExecutionStarted Tests
    // =========================================================================

    public function testToolExecutionStartedConstruction(): void
    {
        $timestamp = microtime(true);
        $event = new ToolExecutionStarted(
            toolName: 'test_tool',
            arguments: ['foo' => 'bar'],
            context: $this->context,
            timestamp: $timestamp,
        );

        $this->assertSame('test_tool', $event->toolName);
        $this->assertSame(['foo' => 'bar'], $event->arguments);
        $this->assertSame($this->context, $event->context);
        $this->assertSame($timestamp, $event->timestamp);
    }

    public function testToolExecutionStartedGetElapsedMs(): void
    {
        $timestamp = microtime(true) - 0.1; // 100ms ago
        $event = new ToolExecutionStarted(
            toolName: 'test_tool',
            arguments: [],
            context: $this->context,
            timestamp: $timestamp,
        );

        $elapsed = $event->getElapsedMs();
        $this->assertGreaterThanOrEqual(100, $elapsed);
        $this->assertLessThan(200, $elapsed); // Should be around 100ms
    }

    public function testToolExecutionStartedToArray(): void
    {
        $timestamp = 1700000000.123;
        $event = new ToolExecutionStarted(
            toolName: 'my_tool',
            arguments: ['arg' => 'value'],
            context: $this->context,
            timestamp: $timestamp,
        );

        $array = $event->toArray();

        $this->assertSame('tool_execution_started', $array['event']);
        $this->assertSame('my_tool', $array['tool_name']);
        $this->assertSame('req-789', $array['request_id']);
        $this->assertSame($timestamp, $array['timestamp']);
    }

    // =========================================================================
    // ToolExecutionSucceeded Tests
    // =========================================================================

    public function testToolExecutionSucceededConstruction(): void
    {
        $result = ToolResult::success('Done', ['data' => 'value']);
        $startTime = 1700000000.0;
        $endTime = 1700000000.5;

        $event = new ToolExecutionSucceeded(
            toolName: 'success_tool',
            arguments: ['x' => 'y'],
            result: $result,
            context: $this->context,
            startTime: $startTime,
            endTime: $endTime,
        );

        $this->assertSame('success_tool', $event->toolName);
        $this->assertSame(['x' => 'y'], $event->arguments);
        $this->assertSame($result, $event->result);
        $this->assertSame($this->context, $event->context);
        $this->assertSame($startTime, $event->startTime);
        $this->assertSame($endTime, $event->endTime);
    }

    public function testToolExecutionSucceededGetDurationMs(): void
    {
        $result = ToolResult::success('Done');
        $event = new ToolExecutionSucceeded(
            toolName: 'test',
            arguments: [],
            result: $result,
            context: $this->context,
            startTime: 1700000000.0,
            endTime: 1700000000.25, // 250ms later
        );

        $this->assertSame(250.0, $event->getDurationMs());
    }

    public function testToolExecutionSucceededToArray(): void
    {
        $result = ToolResult::success('Done');
        $event = new ToolExecutionSucceeded(
            toolName: 'array_tool',
            arguments: [],
            result: $result,
            context: $this->context,
            startTime: 1700000000.0,
            endTime: 1700000000.1,
        );

        $array = $event->toArray();

        $this->assertSame('tool_execution_succeeded', $array['event']);
        $this->assertSame('array_tool', $array['tool_name']);
        $this->assertSame('req-789', $array['request_id']);
        $this->assertSame(100.0, $array['duration_ms']);
        $this->assertSame(1700000000.0, $array['start_time']);
        $this->assertSame(1700000000.1, $array['end_time']);
    }

    public function testToolExecutionSucceededToMetrics(): void
    {
        $result = ToolResult::success('Done');
        $event = new ToolExecutionSucceeded(
            toolName: 'metrics_tool',
            arguments: [],
            result: $result,
            context: $this->context,
            startTime: 1700000000.0,
            endTime: 1700000000.05,
        );

        $metrics = $event->toMetrics();

        $this->assertSame('metrics_tool', $metrics['tool']);
        $this->assertTrue($metrics['success']);
        $this->assertSame(50.0, $metrics['duration_ms']);
    }

    // =========================================================================
    // ToolExecutionFailed Tests
    // =========================================================================

    public function testToolExecutionFailedConstructionWithoutException(): void
    {
        $event = new ToolExecutionFailed(
            toolName: 'failed_tool',
            arguments: ['input' => 'bad'],
            error: 'Validation failed',
            context: $this->context,
            startTime: 1700000000.0,
            endTime: 1700000000.01,
        );

        $this->assertSame('failed_tool', $event->toolName);
        $this->assertSame(['input' => 'bad'], $event->arguments);
        $this->assertSame('Validation failed', $event->error);
        $this->assertSame($this->context, $event->context);
        $this->assertSame(1700000000.0, $event->startTime);
        $this->assertSame(1700000000.01, $event->endTime);
        $this->assertNull($event->exception);
    }

    public function testToolExecutionFailedConstructionWithException(): void
    {
        $exception = new \RuntimeException('Boom!');
        $event = new ToolExecutionFailed(
            toolName: 'crash_tool',
            arguments: [],
            error: 'Boom!',
            context: $this->context,
            startTime: 1700000000.0,
            endTime: 1700000000.02,
            exception: $exception,
        );

        $this->assertSame($exception, $event->exception);
    }

    public function testToolExecutionFailedGetDurationMs(): void
    {
        $event = new ToolExecutionFailed(
            toolName: 'test',
            arguments: [],
            error: 'Error',
            context: $this->context,
            startTime: 1700000000.0,
            endTime: 1700000000.123,
        );

        $this->assertSame(123.0, $event->getDurationMs());
    }

    public function testToolExecutionFailedHasException(): void
    {
        $eventWithout = new ToolExecutionFailed(
            toolName: 'test',
            arguments: [],
            error: 'Error',
            context: $this->context,
            startTime: 0.0,
            endTime: 0.0,
        );

        $eventWith = new ToolExecutionFailed(
            toolName: 'test',
            arguments: [],
            error: 'Error',
            context: $this->context,
            startTime: 0.0,
            endTime: 0.0,
            exception: new \Exception('Test'),
        );

        $this->assertFalse($eventWithout->hasException());
        $this->assertTrue($eventWith->hasException());
    }

    public function testToolExecutionFailedToArrayWithoutException(): void
    {
        $event = new ToolExecutionFailed(
            toolName: 'fail_tool',
            arguments: [],
            error: 'Something broke',
            context: $this->context,
            startTime: 1700000000.0,
            endTime: 1700000000.05,
        );

        $array = $event->toArray();

        $this->assertSame('tool_execution_failed', $array['event']);
        $this->assertSame('fail_tool', $array['tool_name']);
        $this->assertSame('req-789', $array['request_id']);
        $this->assertSame('Something broke', $array['error']);
        $this->assertSame(50.0, $array['duration_ms']);
        $this->assertSame(1700000000.0, $array['start_time']);
        $this->assertSame(1700000000.05, $array['end_time']);
        $this->assertArrayNotHasKey('exception_class', $array);
    }

    public function testToolExecutionFailedToArrayWithException(): void
    {
        $event = new ToolExecutionFailed(
            toolName: 'fail_tool',
            arguments: [],
            error: 'Boom',
            context: $this->context,
            startTime: 0.0,
            endTime: 0.0,
            exception: new \InvalidArgumentException('Bad arg'),
        );

        $array = $event->toArray();

        $this->assertSame('InvalidArgumentException', $array['exception_class']);
    }

    public function testToolExecutionFailedToMetricsWithoutException(): void
    {
        $event = new ToolExecutionFailed(
            toolName: 'metrics_fail',
            arguments: [],
            error: 'Error',
            context: $this->context,
            startTime: 1700000000.0,
            endTime: 1700000000.02,
        );

        $metrics = $event->toMetrics();

        $this->assertSame('metrics_fail', $metrics['tool']);
        $this->assertFalse($metrics['success']);
        $this->assertSame(20.0, $metrics['duration_ms']);
        $this->assertSame('tool_error', $metrics['error_type']);
    }

    public function testToolExecutionFailedToMetricsWithException(): void
    {
        $event = new ToolExecutionFailed(
            toolName: 'metrics_crash',
            arguments: [],
            error: 'Error',
            context: $this->context,
            startTime: 0.0,
            endTime: 0.0,
            exception: new \LogicException('Logic error'),
        );

        $metrics = $event->toMetrics();

        $this->assertSame('LogicException', $metrics['error_type']);
    }
}
