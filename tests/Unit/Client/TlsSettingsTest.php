<?php

declare(strict_types=1);

namespace Neili\Tests\Unit\Client;

use Amp\Cancellation;
use Amp\Socket\ConnectContext;
use Amp\Socket\ConnectException;
use Amp\Socket\Socket;
use Amp\Socket\SocketAddress;
use Amp\Socket\SocketConnector;
use Neili\Client;
use Neili\Exceptions\TransientException;
use Neili\Settings;
use Neili\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;
use function Amp\Socket\socketConnector;

final class TlsSettingsTest extends TestCase
{
    public function testTlsSettingReachesConnectionAndIsAppliedAgainOnReconnect(): void
    {
        $connector = new class implements SocketConnector {
            public array $contexts = [];
            public function connect(SocketAddress|string $uri, ?ConnectContext $context = null,
                ?Cancellation $cancellation = null): Socket
            {
                $this->contexts[] = $context;
                throw new ConnectException('Intentional connection failure; no network request');
            }
        };
        $previous = socketConnector();
        socketConnector($connector);
        try {
            $settings = (new Settings())->setAccessToken('test')->setApiUrl('https://example.test/bot');
            $client = new Client($settings);
            $this->attempt($client);
            self::assertNotEmpty($connector->contexts);
            foreach ($connector->contexts as $context) {
                self::assertTrue($context->getTlsContext()->hasPeerVerification());
                self::assertTrue($context->getTlsContext()->hasPeerNameVerification());
                self::assertSame('example.test', $context->getTlsContext()->getPeerName());
            }

            $connector->contexts = [];
            $settings->setApiVerifySSL(false);
            $client->reconnect();
            $this->attempt($client);
            self::assertNotEmpty($connector->contexts);
            foreach ($connector->contexts as $context) {
                self::assertFalse($context->getTlsContext()->hasPeerVerification());
                self::assertFalse($context->getTlsContext()->hasPeerNameVerification());
            }

            $connector->contexts = [];
            $settings->setApiVerifySSL(true);
            $client->reconnect();
            $this->attempt($client);
            self::assertTrue($connector->contexts[0]->getTlsContext()->hasPeerVerification());
        } finally {
            socketConnector($previous);
        }
    }

    private function attempt(Client $client): void
    {
        try {
            $client->getMe()->await();
            self::fail('Expected intentional connection error');
        } catch (TransientException $error) {
            self::assertStringContainsString('Connection', $error->getMessage());
        }
    }

    public function testInjectedClientIsPreservedAcrossReconnect(): void
    {
        $http = new FakeHttpClient();
        $http->pushJsonResponse(['ok' => true, 'result' => true]);
        $http->pushJsonResponse(['ok' => true, 'result' => true]);
        $client = new Client((new Settings())->setAccessToken('test')->setApiVerifySSL(false)->setHttpClient($http));
        $client->getMe()->await();
        $client->reconnect();
        $client->getMe()->await();
        self::assertCount(2, $http->getRequests());
    }
}
