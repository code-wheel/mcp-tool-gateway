<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway\Tests;

use CodeWheel\McpToolGateway\ToolExecutionException;
use CodeWheel\McpToolGateway\ToolNotFoundException;
use PHPUnit\Framework\TestCase;

class ExceptionTest extends TestCase
{
    public function testToolNotFoundExceptionWithDefaultMessage(): void
    {
        $exception = new ToolNotFoundException('my-tool');

        $this->assertSame('my-tool', $exception->toolName);
        $this->assertSame('Tool not found: my-tool', $exception->getMessage());
    }

    public function testToolNotFoundExceptionWithCustomMessage(): void
    {
        $exception = new ToolNotFoundException('my-tool', 'Custom message');

        $this->assertSame('my-tool', $exception->toolName);
        $this->assertSame('Custom message', $exception->getMessage());
    }

    public function testToolNotFoundExceptionWithCodeAndPrevious(): void
    {
        $previous = new \Exception('Previous error');
        $exception = new ToolNotFoundException('my-tool', 'Error', 42, $previous);

        $this->assertSame(42, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testToolNotFoundExceptionIsRuntimeException(): void
    {
        $exception = new ToolNotFoundException('my-tool');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testToolExecutionExceptionWithDefaultMessage(): void
    {
        $exception = new ToolExecutionException('my-tool');

        $this->assertSame('my-tool', $exception->toolName);
        $this->assertSame('Failed to execute tool: my-tool', $exception->getMessage());
        $this->assertSame([], $exception->context);
    }

    public function testToolExecutionExceptionWithCustomMessage(): void
    {
        $exception = new ToolExecutionException('my-tool', 'Something went wrong');

        $this->assertSame('Something went wrong', $exception->getMessage());
    }

    public function testToolExecutionExceptionWithContext(): void
    {
        $context = ['field' => 'email', 'error' => 'invalid format'];
        $exception = new ToolExecutionException('my-tool', 'Validation failed', $context);

        $this->assertSame($context, $exception->context);
    }

    public function testToolExecutionExceptionWithCodeAndPrevious(): void
    {
        $previous = new \Exception('Database error');
        $exception = new ToolExecutionException('my-tool', 'Error', [], 500, $previous);

        $this->assertSame(500, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testToolExecutionExceptionIsRuntimeException(): void
    {
        $exception = new ToolExecutionException('my-tool');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
}
