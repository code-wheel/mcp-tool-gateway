<?php

declare(strict_types=1);

namespace CodeWheel\McpToolGateway\Tests;

use CodeWheel\McpToolGateway\ExecutionContext;
use PHPUnit\Framework\TestCase;

class ExecutionContextTest extends TestCase
{
    public function testConstructorWithDefaults(): void
    {
        $context = new ExecutionContext();

        $this->assertNull($context->userId);
        $this->assertSame([], $context->attributes);
    }

    public function testConstructorWithUserId(): void
    {
        $context = new ExecutionContext(userId: 'user-123');

        $this->assertSame('user-123', $context->userId);
    }

    public function testConstructorWithAttributes(): void
    {
        $context = new ExecutionContext(
            userId: 'user-123',
            attributes: ['role' => 'admin', 'tenant' => 'acme']
        );

        $this->assertSame('user-123', $context->userId);
        $this->assertSame(['role' => 'admin', 'tenant' => 'acme'], $context->attributes);
    }

    public function testGet(): void
    {
        $context = new ExecutionContext(
            attributes: ['key1' => 'value1', 'key2' => 123]
        );

        $this->assertSame('value1', $context->get('key1'));
        $this->assertSame(123, $context->get('key2'));
    }

    public function testGetWithDefault(): void
    {
        $context = new ExecutionContext();

        $this->assertSame('default', $context->get('nonexistent', 'default'));
        $this->assertNull($context->get('nonexistent'));
    }

    public function testWith(): void
    {
        $context = new ExecutionContext(
            userId: 'user-123',
            attributes: ['key1' => 'value1']
        );

        $newContext = $context->with('key2', 'value2');

        // Original unchanged
        $this->assertNull($context->get('key2'));

        // New context has both
        $this->assertSame('user-123', $newContext->userId);
        $this->assertSame('value1', $newContext->get('key1'));
        $this->assertSame('value2', $newContext->get('key2'));
    }

    public function testWithOverwritesExisting(): void
    {
        $context = new ExecutionContext(attributes: ['key' => 'original']);
        $newContext = $context->with('key', 'overwritten');

        $this->assertSame('original', $context->get('key'));
        $this->assertSame('overwritten', $newContext->get('key'));
    }

    public function testImmutability(): void
    {
        $context1 = new ExecutionContext(userId: 'user-1');
        $context2 = $context1->with('attr', 'value');

        $this->assertNotSame($context1, $context2);
        $this->assertSame('user-1', $context2->userId);
    }
}
