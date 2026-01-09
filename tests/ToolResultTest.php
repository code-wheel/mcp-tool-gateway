<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway\Tests;

use CodeWheel\McpToolGateway\ToolResult;
use PHPUnit\Framework\TestCase;

class ToolResultTest extends TestCase
{
    public function testSuccessFactory(): void
    {
        $result = ToolResult::success('Operation completed', ['id' => 123]);

        $this->assertTrue($result->success);
        $this->assertSame('Operation completed', $result->message);
        $this->assertSame(['id' => 123], $result->data);
        $this->assertFalse($result->isError);
    }

    public function testErrorFactory(): void
    {
        $result = ToolResult::error('Something failed', ['code' => 500]);

        $this->assertFalse($result->success);
        $this->assertSame('Something failed', $result->message);
        $this->assertSame(['code' => 500], $result->data);
        $this->assertTrue($result->isError);
    }

    public function testToArray(): void
    {
        $result = ToolResult::success('Done', ['foo' => 'bar', 'count' => 5]);
        $array = $result->toArray();

        $this->assertTrue($array['success']);
        $this->assertSame('Done', $array['message']);
        $this->assertSame('bar', $array['foo']);
        $this->assertSame(5, $array['count']);
    }

    public function testToArrayWithError(): void
    {
        $result = ToolResult::error('Failed');
        $array = $result->toArray();

        $this->assertFalse($array['success']);
        $this->assertSame('Failed', $array['message']);
    }

    public function testDirectConstruction(): void
    {
        $result = new ToolResult(
            success: true,
            message: 'Custom',
            data: ['x' => 1],
            isError: false,
        );

        $this->assertTrue($result->success);
        $this->assertSame('Custom', $result->message);
        $this->assertSame(['x' => 1], $result->data);
        $this->assertFalse($result->isError);
    }
}
