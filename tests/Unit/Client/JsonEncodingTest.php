<?php

declare(strict_types=1);

namespace Neili\Tests\Unit\Client;

use Neili\Client;
use Neili\Exceptions\PermanentException;
use Neili\Media;
use Neili\Settings;
use Neili\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JsonEncodingTest extends TestCase
{
    public static function invalidPayloads(): iterable
    {
        yield 'invalid UTF-8' => [['text' => "\xB1"]];
        yield 'non-finite number' => [['number' => INF]];
        $recursive = [];
        $recursive['self'] = &$recursive;
        yield 'recursive array' => [$recursive];
    }

    #[DataProvider('invalidPayloads')]
    public function testInvalidPayloadFailsPermanentlyBeforeSending(array $payload): void
    {
        $http = new FakeHttpClient();
        $client = new Client((new Settings())->setAccessToken('test')->setHttpClient($http));
        try {
            $client->__call('exampleMethod', [$payload])->await();
            self::fail('Expected JSON encoding error');
        } catch (PermanentException $error) {
            self::assertInstanceOf(\JsonException::class, $error->getPrevious());
            self::assertStringContainsString('Invalid JSON request payload', $error->getMessage());
        }
        self::assertCount(0, $http->getRequests());
        $client->flush();
    }

    public function testInvalidNestedKeyboardDoesNotSilentlyBecomeFalse(): void
    {
        $http = new FakeHttpClient();
        $client = new Client((new Settings())->setAccessToken('test')->setHttpClient($http));
        try {
            $client->sendMessage(1, 'valid text', keyboard: ['keyboard' => [["\xB1"]]])->await();
            self::fail('Expected nested JSON encoding error');
        } catch (PermanentException $error) {
            self::assertInstanceOf(\JsonException::class, $error->getPrevious());
        }
        self::assertCount(0, $http->getRequests());
    }

    public function testMultipartEncodesStructuredAndBooleanExtraParameters(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'neili_json_');
        file_put_contents($file, 'image');
        try {
            $http = new FakeHttpClient();
            $http->pushJsonResponse(['ok' => true, 'result' => true]);
            $client = new Client((new Settings())->setAccessToken('test')->setHttpClient($http));
            $client->sendPhoto(1, new Media($file), extraParams: [
                'protect_content' => false, 'has_spoiler' => true, 'structured' => ['key' => 'value'],
            ])->await();
            $body = $http->getLastRequestBody();
            self::assertStringContainsString("name=\"protect_content\"\r\n\r\nfalse\r\n", $body);
            self::assertStringContainsString("name=\"has_spoiler\"\r\n\r\ntrue\r\n", $body);
            self::assertStringContainsString('{"key":"value"}', $body);
        } finally {
            unlink($file);
        }
    }

    public function testUtf8TextRemainsValid(): void
    {
        $http = new FakeHttpClient();
        $http->pushJsonResponse(['ok' => true, 'result' => true]);
        $client = new Client((new Settings())->setAccessToken('test')->setHttpClient($http));
        $client->sendMessage(1, 'سلام 👋')->await();
        self::assertSame('سلام 👋', $http->getLastRequestPayload()['text']);
    }
}
