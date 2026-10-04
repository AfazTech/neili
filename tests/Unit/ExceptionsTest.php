<?php

declare(strict_types=1);

namespace Neili\Tests\Unit;

use Neili\Exceptions\NeiliException;
use Neili\Exceptions\PermanentException;
use Neili\Exceptions\RateLimitException;
use Neili\Exceptions\TransientException;
use PHPUnit\Framework\TestCase;

final class ExceptionsTest extends TestCase
{
    public function testTransientExceptionExtendsBase(): void
    {
        $e = new TransientException('boom', 503);
        self::assertInstanceOf(NeiliException::class, $e);
        self::assertSame('boom', $e->getMessage());
        self::assertSame(503, $e->getCode());
    }

    public function testPermanentExceptionExposesErrorCodeAndParameters(): void
    {
        $e = new PermanentException('bad request', 400, ['foo' => 'bar']);
        self::assertSame(400, $e->getErrorCode());
        self::assertSame(['foo' => 'bar'], $e->getParameters());
        self::assertSame(400, $e->getCode());
    }

    public function testRateLimitExceptionClampsRetryAfterToMinimumOne(): void
    {
        $e = new RateLimitException('too fast', 0);
        self::assertSame(1, $e->getRetryAfter());
    }

    public function testRateLimitExceptionKeepsPositiveRetryAfter(): void
    {
        $e = new RateLimitException('too fast', 30, ['retry_after' => 30]);
        self::assertSame(30, $e->getRetryAfter());
        self::assertSame(['retry_after' => 30], $e->getParameters());
        self::assertSame(429, $e->getCode());
    }
}
