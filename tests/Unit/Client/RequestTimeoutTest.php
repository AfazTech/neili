<?php

declare(strict_types=1);

namespace Neili\Tests\Unit\Client;

use Neili\Client;
use Neili\Media;
use Neili\Settings;
use Neili\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class RequestTimeoutTest extends TestCase
{
    private function client(FakeHttpClient $http): Client
    {
        $http->pushJsonResponse(['ok' => true, 'result' => []]);
        return new Client((new Settings())->setAccessToken('test')->setHttpClient($http)
            ->setTimeout(17, 4)->setPollerTimeout(25));
    }

    private function assertTimeouts(FakeHttpClient $http, float $transfer): void
    {
        $request = $http->getLastRequest();
        self::assertSame($transfer, $request->getTransferTimeout());
        self::assertSame($transfer, $request->getInactivityTimeout());
        self::assertSame(4.0, $request->getTcpConnectTimeout());
    }

    public function testJsonRequestUsesConfiguredTimeouts(): void
    {
        $http = new FakeHttpClient();
        $this->client($http)->getMe()->await();
        $this->assertTimeouts($http, 17.0);
    }

    public function testMultipartRequestUsesConfiguredTimeouts(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'neili_timeout_');
        try {
            $http = new FakeHttpClient();
            $this->client($http)->sendDocument(1, new Media($file))->await();
            $this->assertTimeouts($http, 17.0);
        } finally {
            unlink($file);
        }
    }

    public function testExplicitLongPollTimeoutAddsTransportGrace(): void
    {
        $http = new FakeHttpClient();
        $this->client($http)->getUpdates(timeout: 40)->await();
        $this->assertTimeouts($http, 50.0);
    }

    public function testDefaultLongPollTimeoutUsesPollerSettings(): void
    {
        $http = new FakeHttpClient();
        $this->client($http)->getUpdates()->await();
        $this->assertTimeouts($http, 35.0);
    }

    public function testShortPollStillHasTransportGrace(): void
    {
        $http = new FakeHttpClient();
        $this->client($http)->getUpdates(timeout: 0)->await();
        $this->assertTimeouts($http, 10.0);
    }
}
