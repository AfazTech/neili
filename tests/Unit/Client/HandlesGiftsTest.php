<?php

declare(strict_types=1);

namespace Neili\Tests\Unit\Client;

use Neili\Client;
use Neili\Settings;
use Neili\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class HandlesGiftsTest extends TestCase
{
    private function makeClient(FakeHttpClient $http): Client
    {
        $settings = (new Settings())
            ->setAccessToken('123:abc')
            ->setHttpClient($http);

        return new Client($settings);
    }

    public function testSendGiftRequiresExactlyOneTarget(): void
    {
        $http = new FakeHttpClient();
        $client = $this->makeClient($http);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Exactly one of user_id or chat_id must be provided');

        $client->sendGift(null, null, 'gift-1')->await();
    }

    public function testSendGiftRejectsBothTargets(): void
    {
        $http = new FakeHttpClient();
        $client = $this->makeClient($http);

        $this->expectException(\InvalidArgumentException::class);

        $client->sendGift(1, 2, 'gift-1')->await();
    }

    public function testSendGiftToUser(): void
    {
        $http = new FakeHttpClient();
        $http->pushJsonResponse(['ok' => true, 'result' => true]);

        $client = $this->makeClient($http);
        $client->sendGift(1, null, 'gift-1', true)->await();

        $payload = $http->getLastRequestPayload();

        self::assertSame(1, $payload['user_id']);
        self::assertArrayNotHasKey('chat_id', $payload);
        self::assertSame('gift-1', $payload['gift_id']);
        self::assertTrue($payload['pay_for_upgrade']);
    }

    public function testSendGiftToChat(): void
    {
        $http = new FakeHttpClient();
        $http->pushJsonResponse(['ok' => true, 'result' => true]);

        $client = $this->makeClient($http);
        $client->sendGift(null, '@channel', 'gift-1')->await();

        $payload = $http->getLastRequestPayload();

        self::assertSame('@channel', $payload['chat_id']);
        self::assertArrayNotHasKey('user_id', $payload);
    }
}
