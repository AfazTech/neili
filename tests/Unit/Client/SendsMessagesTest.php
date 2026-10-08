<?php

declare(strict_types=1);

namespace Neili\Tests\Unit\Client;

use Neili\Client;
use Neili\Exceptions\PermanentException;
use Neili\Exceptions\RateLimitException;
use Neili\Exceptions\TransientException;
use Neili\Settings;
use Neili\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class SendsMessagesTest extends TestCase
{
    private function makeClient(FakeHttpClient $http): Client
    {
        $settings = (new Settings())
            ->setAccessToken('123:abc')
            ->setApiUrl('https://api.telegram.org/bot')
            ->setHttpClient($http);

        return new Client($settings);
    }

    public function testSendMessageProducesCorrectPayload(): void
    {
        $http = new FakeHttpClient();
        $http->pushJsonResponse(['ok' => true, 'result' => ['message_id' => 1]]);

        $client = $this->makeClient($http);
        $result = $client->sendMessage(42, 'hello')->await();

        self::assertTrue($result['ok']);
        self::assertSame(1, $result['result']['message_id']);

        $request = $http->getLastRequest();
        self::assertNotNull($request);
        self::assertSame(
            'https://api.telegram.org/bot123:abc/sendMessage',
            (string) $request->getUri()
        );

        $payload = $http->getLastRequestPayload();
        self::assertSame(42, $payload['chat_id']);
        self::assertSame('hello', $payload['text']);
    }

    public function testSendMessageIncludesKeyboardAndExtraParams(): void
    {
        $http = new FakeHttpClient();
        $http->pushJsonResponse(['ok' => true, 'result' => []]);

        $client = $this->makeClient($http);
        $client->sendMessage(
            42,
            'hello',
            ['inline_keyboard' => [[['text' => 'a', 'callback_data' => 'b']]]],
            ['parse_mode' => 'HTML'],
        )->await();

        $payload = $http->getLastRequestPayload();

        self::assertSame('HTML', $payload['parse_mode']);
        self::assertArrayHasKey('reply_markup', $payload);

        $keyboard = json_decode($payload['reply_markup'], true);
        self::assertSame('a', $keyboard['inline_keyboard'][0][0]['text']);
    }

    public function testSendMessageWithThreadAndReplyParameters(): void
    {
        $http = new FakeHttpClient();
        $http->pushJsonResponse(['ok' => true, 'result' => []]);

        $client = $this->makeClient($http);
        $client->sendMessage(
            42,
            'hi',
            messageThreadId: 7,
            replyParameters: ['message_id' => 99],
            disableNotification: true,
        )->await();

        $payload = $http->getLastRequestPayload();

        self::assertSame(7, $payload['message_thread_id']);
        self::assertTrue($payload['disable_notification']);

        $reply = json_decode($payload['reply_parameters'], true);
        self::assertSame(99, $reply['message_id']);
    }

    public function testReplyUsesReplyParameters(): void
    {
        $http = new FakeHttpClient();
        $http->pushJsonResponse(['ok' => true, 'result' => []]);

        $client = $this->makeClient($http);
        $client->reply(42, 99, 'pong')->await();

        $payload = $http->getLastRequestPayload();
        $reply = json_decode($payload['reply_parameters'], true);

        self::assertSame(99, $reply['message_id']);
    }

    public function testApiErrorIsTranslatedToPermanentException(): void
    {
        $http = new FakeHttpClient();
        $http->pushJsonResponse([
            'ok' => false,
            'error_code' => 400,
            'description' => 'Bad Request: chat not found',
        ]);

        $client = $this->makeClient($http);

        $this->expectException(PermanentException::class);
        $this->expectExceptionMessage('Bad Request: chat not found');

        $client->sendMessage(42, 'hello')->await();
    }

    public function testRateLimitIsTranslatedToRateLimitException(): void
    {
        $http = new FakeHttpClient();
        $http->pushJsonResponse([
            'ok' => false,
            'error_code' => 429,
            'description' => 'Too Many Requests',
            'parameters' => ['retry_after' => 15],
        ]);

        $client = $this->makeClient($http);

        try {
            $client->sendMessage(42, 'hello')->await();
            self::fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            self::assertSame(15, $e->getRetryAfter());
        }
    }

    public function testHttp5xxIsTranslatedToTransientException(): void
    {
        $http = new FakeHttpClient();
        $http->pushResponse(503, 'Service Unavailable');

        $client = $this->makeClient($http);

        $this->expectException(TransientException::class);

        $client->sendMessage(42, 'hello')->await();
    }

    public function testEditMessageTextRequiresTextOrRichMessage(): void
    {
        $http = new FakeHttpClient();
        $client = $this->makeClient($http);

        $this->expectException(\InvalidArgumentException::class);

        $client->editMessageText(42, 10);
    }

    public function testEditMessageTextWithRichMessage(): void
    {
        $http = new FakeHttpClient();
        $http->pushJsonResponse(['ok' => true, 'result' => []]);

        $client = $this->makeClient($http);
        $client->editMessageText(
            42,
            10,
            richMessage: ['html' => '<b>hi</b>'],
        )->await();

        $payload = $http->getLastRequestPayload();

        self::assertArrayNotHasKey('text', $payload);
        self::assertArrayHasKey('rich_message', $payload);

        $rich = json_decode($payload['rich_message'], true);
        self::assertSame('<b>hi</b>', $rich['html']);
    }
}
