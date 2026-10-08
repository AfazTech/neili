<?php

declare(strict_types=1);

namespace Neili\Tests\Unit\Client;

use Neili\Client;
use Neili\Settings;
use Neili\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class HandlesInlineQueriesTest extends TestCase
{
    private function makeClient(FakeHttpClient $http): Client
    {
        $settings = (new Settings())
            ->setAccessToken('123:abc')
            ->setHttpClient($http);

        return new Client($settings);
    }

    public function testAnswerInlineQueryIncludesResults(): void
    {
        $http = new FakeHttpClient();
        $http->pushJsonResponse(['ok' => true, 'result' => true]);

        $client = $this->makeClient($http);
        $client->answerInlineQuery('q1', [
            [
                'type' => 'article',
                'id' => '1',
                'title' => 'T',
                'input_message_content' => ['message_text' => 'x'],
            ],
        ])->await();

        $payload = $http->getLastRequestPayload();

        self::assertSame('q1', $payload['inline_query_id']);
        $results = json_decode($payload['results'], true);
        self::assertSame('article', $results[0]['type']);
    }

    public function testAnswerInlineQueryWithPaginationAndButton(): void
    {
        $http = new FakeHttpClient();
        $http->pushJsonResponse(['ok' => true, 'result' => true]);

        $client = $this->makeClient($http);
        $client->answerInlineQuery(
            'q1',
            [],
            null,
            null,
            null,
            'next-page-token',
            ['text' => 'Connect', 'start_parameter' => 'go'],
        )->await();

        $payload = $http->getLastRequestPayload();

        self::assertSame('next-page-token', $payload['next_offset']);
        $button = json_decode($payload['button'], true);
        self::assertSame('Connect', $button['text']);
        self::assertSame('go', $button['start_parameter']);
    }

    public function testAnswerCallbackQuery(): void
    {
        $http = new FakeHttpClient();
        $http->pushJsonResponse(['ok' => true, 'result' => true]);

        $client = $this->makeClient($http);
        $client->answerCallbackQuery('cb1', 'Got it', true)->await();

        $payload = $http->getLastRequestPayload();

        self::assertSame('cb1', $payload['callback_query_id']);
        self::assertSame('Got it', $payload['text']);
        self::assertTrue($payload['show_alert']);
    }

    public function testAnswerCallbackQueryWithUrlAndCacheTime(): void
    {
        $http = new FakeHttpClient();
        $http->pushJsonResponse(['ok' => true, 'result' => true]);

        $client = $this->makeClient($http);
        $client->answerCallbackQuery(
            'cb1',
            'Open',
            false,
            null,
            'https://t.me/example',
            10,
        )->await();

        $payload = $http->getLastRequestPayload();

        self::assertSame('https://t.me/example', $payload['url']);
        self::assertSame(10, $payload['cache_time']);
    }
}
