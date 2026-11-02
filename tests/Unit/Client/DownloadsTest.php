<?php

declare(strict_types=1);

namespace Neili\Tests\Unit\Client;

use Amp\ByteStream\ReadableIterableStream;
use Neili\Client;
use Neili\Settings;
use Neili\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class DownloadsTest extends TestCase
{
    private function client(FakeHttpClient $http, string $api = 'https://api.telegram.org/bot'): Client
    {
        return new Client((new Settings())->setAccessToken('test')->setApiUrl($api)->setHttpClient($http));
    }

    public static function endpoints(): iterable
    {
        yield ['https://api.telegram.org/bot', 'https://api.telegram.org/file/bottest/photos/a.jpg'];
        yield ['http://localhost:8081/bot/', 'http://localhost:8081/file/bottest/photos/a.jpg'];
        yield ['https://example.test/custom/', 'https://example.test/customtest/photos/a.jpg'];
    }

    #[DataProvider('endpoints')]
    public function testFileUrlUsesConfiguredEndpoint(string $api, string $expected): void
    {
        self::assertSame($expected, $this->client(new FakeHttpClient(), $api)->getFileUrl('/photos/a.jpg'));
    }

    public function testDownloadStreamsChunksToChosenDestination(): void
    {
        $http = new FakeHttpClient();
        $http->pushJsonResponse(['ok' => true, 'result' => ['file_path' => 'docs/test.bin']]);
        $http->pushStreamResponse(200, new ReadableIterableStream((function () {
            yield "first\0";
            \Amp\delay(0.001);
            yield "second\xff";
        })()));
        $path = tempnam(sys_get_temp_dir(), 'neili_download_');
        file_put_contents($path, 'old contents');
        try {
            self::assertSame($path, $this->client($http)->downloadFile('id', $path)->await());
            self::assertSame("first\0second\xff", file_get_contents($path));
            self::assertSame('GET', $http->getLastRequest()->getMethod());
            self::assertSame('https://api.telegram.org/file/bottest/docs/test.bin', (string) $http->getLastRequest()->getUri());
        } finally {
            unlink($path);
        }
    }

    public function testMissingFilePathDoesNotCreateDestination(): void
    {
        $http = new FakeHttpClient();
        $http->pushJsonResponse(['ok' => true, 'result' => []]);
        $path = sys_get_temp_dir() . '/neili_absent_' . bin2hex(random_bytes(8));
        try {
            $this->client($http)->downloadFile('id', $path)->await();
            self::fail('Expected missing file_path failure');
        } catch (\RuntimeException $error) {
            self::assertSame('Invalid file_id or file not found', $error->getMessage());
        }
        self::assertFileDoesNotExist($path);
        self::assertCount(1, $http->getRequests());
    }

    public function testHttpFailurePreservesExistingDestination(): void
    {
        $http = new FakeHttpClient();
        $http->pushJsonResponse(['ok' => true, 'result' => ['file_path' => 'test.bin']]);
        $http->pushResponse(404, 'not found');
        $path = tempnam(sys_get_temp_dir(), 'neili_download_');
        file_put_contents($path, 'original');
        try {
            try {
                $this->client($http)->downloadFile('id', $path)->await();
                self::fail('Expected HTTP failure');
            } catch (\RuntimeException $error) {
                self::assertSame('Failed to download file: HTTP 404', $error->getMessage());
            }
            self::assertSame('original', file_get_contents($path));
        } finally {
            unlink($path);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testStreamFailureClosesDestinationHandle(): void
    {
        $error = new \RuntimeException('stream interrupted');
        $http = new FakeHttpClient();
        $http->pushJsonResponse(['ok' => true, 'result' => ['file_path' => 'test.bin']]);
        $http->pushStreamResponse(200, new ReadableIterableStream((function () use ($error) {
            yield 'partial';
            throw $error;
        })()));
        $file = $this->createMock(\Amp\File\File::class);
        $file->expects(self::once())->method('write')->with('partial');
        $file->expects(self::once())->method('close');
        $driver = $this->createMock(\Amp\File\FilesystemDriver::class);
        $driver->expects(self::once())->method('openFile')->with('/virtual/output.bin', 'w')->willReturn($file);
        \Amp\File\filesystem($driver);
        try {
            $this->client($http)->downloadFile('id', '/virtual/output.bin')->await();
            self::fail('Expected stream failure');
        } catch (\RuntimeException $caught) {
            self::assertSame($error, $caught);
        }
    }
}
