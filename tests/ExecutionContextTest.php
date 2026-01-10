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

    public function testCreateStaticMethod(): void
    {
        $context = ExecutionContext::create(
            userId: 'user-456',
            scopes: ['read', 'write'],
            attributes: ['key' => 'value']
        );

        // Should generate a request ID
        $this->assertNotNull($context->requestId);
        $this->assertStringStartsWith('req_', (string) $context->requestId);

        $this->assertSame('user-456', $context->userId);
        $this->assertSame(['read', 'write'], $context->scopes);
        $this->assertSame(['key' => 'value'], $context->attributes);
    }

    public function testCreateWithDefaultValues(): void
    {
        $context = ExecutionContext::create();

        $this->assertNotNull($context->requestId);
        $this->assertNull($context->userId);
        $this->assertSame([], $context->scopes);
        $this->assertSame([], $context->attributes);
    }

    public function testRequestIdProperty(): void
    {
        $context = new ExecutionContext(requestId: 'my-request-id');
        $this->assertSame('my-request-id', $context->requestId);

        $contextInt = new ExecutionContext(requestId: 12345);
        $this->assertSame(12345, $contextInt->requestId);
    }

    public function testScopesProperty(): void
    {
        $context = new ExecutionContext(scopes: ['admin', 'read', 'write']);
        $this->assertSame(['admin', 'read', 'write'], $context->scopes);
    }

    public function testHasScope(): void
    {
        $context = new ExecutionContext(scopes: ['read', 'write']);

        $this->assertTrue($context->hasScope('read'));
        $this->assertTrue($context->hasScope('write'));
        $this->assertFalse($context->hasScope('delete'));
        $this->assertFalse($context->hasScope('admin'));
    }

    public function testHasAnyScope(): void
    {
        $context = new ExecutionContext(scopes: ['read', 'write']);

        $this->assertTrue($context->hasAnyScope(['read']));
        $this->assertTrue($context->hasAnyScope(['delete', 'write']));
        $this->assertTrue($context->hasAnyScope(['admin', 'read', 'superuser']));
        $this->assertFalse($context->hasAnyScope(['delete', 'admin']));
        $this->assertFalse($context->hasAnyScope([]));
    }

    public function testToArray(): void
    {
        $context = new ExecutionContext(
            requestId: 'req-abc',
            userId: 'user-xyz',
            scopes: ['read', 'write'],
            attributes: ['tenant' => 'acme']
        );

        $array = $context->toArray();

        $this->assertSame('req-abc', $array['request_id']);
        $this->assertSame('user-xyz', $array['user_id']);
        $this->assertSame(['read', 'write'], $array['scopes']);
        $this->assertSame(['tenant' => 'acme'], $array['attributes']);
    }

    public function testWithPreservesScopes(): void
    {
        $context = new ExecutionContext(
            requestId: 'req-1',
            userId: 'user-1',
            scopes: ['read'],
        );

        $newContext = $context->with('key', 'value');

        $this->assertSame('req-1', $newContext->requestId);
        $this->assertSame(['read'], $newContext->scopes);
        $this->assertSame('value', $newContext->get('key'));
    }
}
