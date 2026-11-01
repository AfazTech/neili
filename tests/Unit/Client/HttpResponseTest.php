<?php

declare(strict_types=1);

namespace Neili\Tests\Unit\Client;

use Neili\Client;
use Neili\Exceptions\PermanentException;
use Neili\Exceptions\RateLimitException;
use Neili\Exceptions\TransientException;
use Neili\Settings;
use Neili\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HttpResponseTest extends TestCase
{
    private function response(int $status, string $body): array
    {
        $http = new FakeHttpClient();
        $http->pushResponse($status, $body);
        return (new Client((new Settings())->setAccessToken('test')->setHttpClient($http)))->getMe()->await();
    }

    public static function permanentResponses(): iterable
    {
        yield 'empty object' => [401, '{}'];
        yield 'HTML proxy response' => [403, '<html>Forbidden</html>'];
        yield 'false success flag' => [400, '{"ok":true,"result":true}'];
        yield 'null response' => [404, 'null'];
    }

    #[DataProvider('permanentResponses')]
    public function testHttpClientErrorsNeverSucceed(int $status, string $body): void
    {
        try {
            $this->response($status, $body);
            self::fail('Expected permanent HTTP failure');
        } catch (PermanentException $error) {
            self::assertSame($status, $error->getErrorCode());
        }
    }

    public static function malformedResponses(): iterable
    {
        foreach (['{}', '[]', 'null', 'true', '"text"', 'invalid JSON', '{"ok":1,"result":true}',
            '{"ok":true}', '{"ok":false}'] as $body) {
            yield [$body];
        }
    }

    #[DataProvider('malformedResponses')]
    public function testMalformedSuccessStatusIsTransient(string $body): void
    {
        $this->expectException(TransientException::class);
        $this->response(200, $body);
    }

    public function testHttpRateLimitWithoutTelegramPayload(): void
    {
        try {
            $this->response(429, '<html>Too many requests</html>');
            self::fail('Expected rate limit');
        } catch (RateLimitException $error) {
            self::assertSame(1, $error->getRetryAfter());
        }
    }

    public function testRateLimitPreservesTelegramParameters(): void
    {
        try {
            $this->response(429, '{"ok":false,"error_code":429,"description":"Slow down","parameters":{"retry_after":12}}');
            self::fail('Expected rate limit');
        } catch (RateLimitException $error) {
            self::assertSame(12, $error->getRetryAfter());
            self::assertSame('Slow down', $error->getMessage());
        }
    }

    public function testServerErrorOverridesSuccessfulPayload(): void
    {
        $this->expectException(TransientException::class);
        $this->response(503, '{"ok":true,"result":true}');
    }

    public function testRedirectIsNotAcceptedAsSuccess(): void
    {
        $this->expectException(TransientException::class);
        $this->response(302, '{"ok":true,"result":true}');
    }

    public function testBooleanResultIsValid(): void
    {
        self::assertSame(['ok' => true, 'result' => true], $this->response(200, '{"ok":true,"result":true}'));
    }
}
