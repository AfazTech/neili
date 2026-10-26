<?php

declare(strict_types=1);

namespace Neili\Tests\Unit\Client;

use Neili\Client;
use Neili\Client\Multipart\MultipartBody;
use Neili\Media;
use Neili\Settings;
use Neili\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class SendsMediaTest extends TestCase
{
    private function makeClient(FakeHttpClient $http): Client
    {
        $settings = (new Settings())
            ->setAccessToken('123:abc')
            ->setHttpClient($http);

        return new Client($settings);
    }

    public function testSendPhotoByUrlSendsJsonPayload(): void
    {
        $http = new FakeHttpClient();
        $http->pushJsonResponse(['ok' => true, 'result' => []]);

        $client = $this->makeClient($http);
        $client->sendPhoto(1, 'https://example.com/photo.jpg')->await();

        $payload = $http->getLastRequestPayload();

        self::assertSame('https://example.com/photo.jpg', $payload['photo']);
    }

    public function testSendPhotoWithLocalMediaSendsMultipart(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'neili_photo_');
        file_put_contents($file, 'fake-jpeg-data');

        try {
            $http = new FakeHttpClient();
            $http->pushJsonResponse(['ok' => true, 'result' => []]);

            $client = $this->makeClient($http);
            $client->sendPhoto(1, new Media($file), 'Caption')->await();

            $request = $http->getLastRequest();
            self::assertNotNull($request);
            self::assertInstanceOf(MultipartBody::class, $request->getBody());

            $body = $http->getLastRequestBody();
            self::assertNotNull($body);
            self::assertStringContainsString('name="chat_id"', $body);
            self::assertStringContainsString('name="caption"', $body);
            self::assertStringContainsString('name="photo"; filename=', $body);
            self::assertStringContainsString('fake-jpeg-data', $body);
        } finally {
            @unlink($file);
        }
    }

    public function testSendPaidMediaRejectsEmptyArray(): void
    {
        $http = new FakeHttpClient();
        $client = $this->makeClient($http);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Paid media items array cannot be empty');

        $client->sendPaidMedia(1, 100, [])->await();
    }

    public function testSendPaidMediaRejectsInvalidItem(): void
    {
        $http = new FakeHttpClient();
        $client = $this->makeClient($http);

        $this->expectException(\InvalidArgumentException::class);

        $client->sendPaidMedia(1, 100, [42])->await();
    }

    public function testSendMediaGroupRejectsMoreThanTenItems(): void
    {
        $http = new FakeHttpClient();
        $client = $this->makeClient($http);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Telegram allows maximum 10 media items');

        $items = array_fill(0, 11, 'https://example.com/photo.jpg');
        $client->sendMediaGroup(1, $items)->await();
    }

    public function testSendMediaGroupRejectsEmptyItems(): void
    {
        $http = new FakeHttpClient();
        $client = $this->makeClient($http);

        $this->expectException(\InvalidArgumentException::class);

        $client->sendMediaGroup(1, [])->await();
    }

    public function testSendMediaGroupLegacyReplyKept(): void
    {
        $http = new FakeHttpClient();
        $http->pushJsonResponse(['ok' => true, 'result' => []]);

        $client = $this->makeClient($http);
        $client->sendMediaGroup(
            1,
            ['https://example.com/a.jpg', 'https://example.com/b.jpg'],
            'cap',
            null,
            42,
        )->await();

        $payload = $http->getLastRequestPayload();
        self::assertSame(42, $payload['reply_to_message_id']);
        self::assertArrayNotHasKey('reply_parameters', $payload);
    }
}
