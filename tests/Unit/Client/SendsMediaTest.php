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

    public function testFileCreatedAfterMediaConstructionCanBeUploaded(): void
    {
        $file = sys_get_temp_dir() . '/neili_deferred_' . bin2hex(random_bytes(8));
        $media = new Media($file);
        file_put_contents($file, 'created later');
        try {
            $http = new FakeHttpClient();
            $http->pushJsonResponse(['ok' => true, 'result' => []]);
            $this->makeClient($http)->sendPhoto(1, $media)->await();
            self::assertStringContainsString('created later', $http->getLastRequestBody());
        } finally {
            unlink($file);
        }
    }

    public function testMissingFileFailsTheFutureBeforeHttpRequest(): void
    {
        $http = new FakeHttpClient();
        $future = $this->makeClient($http)->sendPhoto(1,
            new Media(sys_get_temp_dir() . '/neili_missing_' . bin2hex(random_bytes(8))));
        try {
            $future->await();
            self::fail('Expected missing file error');
        } catch (\Neili\Exceptions\PermanentException $error) {
            self::assertStringContainsString('File not found', $error->getMessage());
            self::assertSame([], $http->getRequests());
        }
    }

    public function testDirectoryCannotBeUploadedAsAFile(): void
    {
        $this->expectException(\Neili\Exceptions\PermanentException::class);
        $this->expectExceptionMessage('Not a regular file');
        $this->makeClient(new FakeHttpClient())->sendPhoto(1, new Media(sys_get_temp_dir()))->await();
    }

    public function testRelativeMediaUploadsAfterWorkingDirectoryChanges(): void
    {
        $directory = sys_get_temp_dir() . '/neili_relative_' . bin2hex(random_bytes(8));
        mkdir($directory);
        file_put_contents($directory . '/photo.jpg', 'relative image');
        $cwd = getcwd();
        try {
            chdir($directory);
            $media = new Media('photo.jpg');
            chdir(dirname($directory));
            $http = new FakeHttpClient();
            $http->pushJsonResponse(['ok' => true, 'result' => []]);
            $this->makeClient($http)->sendPhoto(1, $media)->await();
            self::assertStringContainsString('relative image', $http->getLastRequestBody());
        } finally {
            chdir($cwd);
            unlink($directory . '/photo.jpg');
            rmdir($directory);
        }
    }

    public function testSymlinkUploadPreservesFilenameAndReadsTarget(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'neili_link_target_');
        $link = $file . '.jpg';
        file_put_contents($file, 'symlink target bytes');
        try {
            if (!@symlink($file, $link)) {
                self::markTestSkipped('Symlinks are unavailable');
            }
            $http = new FakeHttpClient();
            $http->pushJsonResponse(['ok' => true, 'result' => []]);
            $this->makeClient($http)->sendPhoto(1, new Media($link))->await();
            self::assertStringContainsString('filename="' . basename($link) . '"', $http->getLastRequestBody());
            self::assertStringContainsString('symlink target bytes', $http->getLastRequestBody());
            self::assertSame(strlen($http->getLastRequestBody()), $http->getLastRequest()->getBody()->getContentLength());
        } finally {
            @unlink($link);
            unlink($file);
        }
    }

    public function testEmptyFileHasExactMultipartLength(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'neili_empty_');
        try {
            $http = new FakeHttpClient();
            $http->pushJsonResponse(['ok' => true, 'result' => []]);
            $this->makeClient($http)->sendDocument(1, new Media($file))->await();
            self::assertSame(strlen($http->getLastRequestBody()), $http->getLastRequest()->getBody()->getContentLength());
        } finally {
            unlink($file);
        }
    }

    public function testDeletedFileFailsBeforeHttpRequest(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'neili_deleted_');
        $media = new Media($file);
        unlink($file);
        $http = new FakeHttpClient();
        try {
            $this->makeClient($http)->sendPhoto(1, $media)->await();
            self::fail('Expected deleted file error');
        } catch (\Neili\Exceptions\PermanentException $error) {
            self::assertStringContainsString('File not found', $error->getMessage());
            self::assertSame([], $http->getRequests());
        }
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testMetadataLookupAllowsOtherFibersToProgress(): void
    {
        $entered = new \Amp\DeferredFuture();
        $gate = new \Amp\DeferredFuture();
        $driver = $this->createMock(\Amp\File\FilesystemDriver::class);
        $driver->expects(self::once())->method('getStatus')->willReturnCallback(
            function () use ($entered, $gate): ?array {
                $entered->complete();
                $gate->getFuture()->await();
                return null;
            }
        );
        \Amp\File\filesystem($driver);
        $http = new FakeHttpClient();
        $upload = $this->makeClient($http)->sendPhoto(1, new Media('/virtual/photo.jpg'));
        $entered->getFuture()->await(new \Amp\TimeoutCancellation(2));
        self::assertFalse($upload->isComplete());
        $other = \Amp\async(static function () use ($gate): string {
            $gate->complete();
            return 'other fiber ran';
        });
        try {
            $upload->await(new \Amp\TimeoutCancellation(2));
            self::fail('Expected missing file error');
        } catch (\Neili\Exceptions\PermanentException $error) {
            self::assertStringContainsString('File not found', $error->getMessage());
        }
        self::assertSame('other fiber ran', $other->await());
        self::assertSame([], $http->getRequests());
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
