<?php

declare(strict_types=1);

namespace Neili\Tests\Unit;

use Amp\Future;
use Neili\{Client, KeyboardBuilder, Media, Poller, Settings};
use Neili\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class DraftEndpointTest extends TestCase
{
    public function testTextAndRichDraftsUseTheirRespectiveEndpoints(): void
    {
        $http = new FakeHttpClient();
        $http->pushJsonResponse(['ok' => true, 'result' => true]);
        $http->pushJsonResponse(['ok' => true, 'result' => true]);
        $client = new Client((new Settings())->setAccessToken('test')->setHttpClient($http));
        $client->sendMessageDraft(42, 1, 'hello')->await();
        self::assertStringEndsWith('/sendMessageDraft', (string) $http->getLastRequest()->getUri());
        self::assertSame('hello', $http->getLastRequestPayload()['text']);
        $client->sendRichMessageDraft(42, 1, ['html' => 'hello'])->await();
        self::assertStringEndsWith('/sendRichMessageDraft', (string) $http->getLastRequest()->getUri());
    }
}
