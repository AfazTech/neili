<?php

declare(strict_types=1);

namespace Neili\Tests\Unit\Client;

use Neili\Client;
use Neili\Media;
use Neili\Settings;
use Neili\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class MultipartAttachmentsTest extends TestCase
{
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) @unlink($file);
    }

    private function media(string $contents): Media
    {
        $file = tempnam(sys_get_temp_dir(), 'neili_attachment_');
        $this->files[] = $file;
        file_put_contents($file, $contents);
        return new Media($file);
    }

    private function client(FakeHttpClient $http): Client
    {
        $http->pushJsonResponse(['ok' => true, 'result' => []]);
        return new Client((new Settings())->setAccessToken('test')->setHttpClient($http));
    }

    /** Parse the serialized body, so references are verified against actual wire parts. */
    private function parts(FakeHttpClient $http): array
    {
        $body = $http->getLastRequestBody();
        $content = $http->getLastRequest()->getBody();
        self::assertSame(strlen($body), $content->getContentLength());
        $boundary = substr($content->getContentType(), strlen('multipart/form-data; boundary='));
        $parts = [];
        foreach (explode('--' . $boundary, $body) as $part) {
            if (!str_contains($part, "\r\n\r\n")) continue;
            [$header, $value] = explode("\r\n\r\n", ltrim($part, "\r\n"), 2);
            preg_match('/name="([^"]+)"/', $header, $match);
            $parts[$match[1]] = substr($value, 0, -2);
        }
        return $parts;
    }

    public function testNestedAlbumFilesHaveDistinctMatchingAttachmentReferences(): void
    {
        $http = new FakeHttpClient();
        $this->client($http)->sendMediaGroup(1, [
            ['type' => 'video', 'media' => $this->media('video-A'), 'thumbnail' => $this->media('thumb-A')],
            ['type' => 'video', 'media' => $this->media('video-B'), 'thumbnail' => $this->media('thumb-B')],
        ])->await();
        $parts = $this->parts($http);
        $media = json_decode($parts['media'], true, 512, JSON_THROW_ON_ERROR);
        $references = [];
        foreach ([['video-A', 'thumb-A'], ['video-B', 'thumb-B']] as $i => [$video, $thumb]) {
            foreach (['media' => $video, 'thumbnail' => $thumb] as $field => $bytes) {
                self::assertStringStartsWith('attach://', $media[$i][$field]);
                $key = substr($media[$i][$field], 9);
                $references[] = $key;
                self::assertSame($bytes, $parts[$key]);
            }
        }
        self::assertCount(4, array_unique($references));
    }

    public function testPollMediaScopesDoNotCollide(): void
    {
        $http = new FakeHttpClient();
        $this->client($http)->sendPoll(1, 'Question', [
            ['text' => 'A', 'media' => ['type' => 'photo', 'photo' => $this->media('option-A')]],
            ['text' => 'B', 'media' => ['type' => 'photo', 'photo' => $this->media('option-B')]],
        ], media: ['type' => 'photo', 'photo' => $this->media('question-photo')],
            explanationMedia: ['type' => 'photo', 'photo' => $this->media('explanation-photo')])->await();
        $parts = $this->parts($http);
        $options = json_decode($parts['options'], true, 512, JSON_THROW_ON_ERROR);
        $question = json_decode($parts['media'], true, 512, JSON_THROW_ON_ERROR);
        $explanation = json_decode($parts['explanation_media'], true, 512, JSON_THROW_ON_ERROR);
        $references = [$options[0]['media']['photo'], $options[1]['media']['photo'],
            $question['photo'], $explanation['photo']];
        self::assertCount(4, array_unique($references));
        foreach (array_combine($references, ['option-A', 'option-B', 'question-photo', 'explanation-photo']) as $reference => $bytes) {
            self::assertStringStartsWith('attach://', $reference);
            $key = substr($reference, 9);
            self::assertArrayHasKey($key, $parts, 'Every attach:// reference must resolve to an actual multipart part');
            self::assertSame($bytes, $parts[$key]);
        }
    }

    public function testMultipartFilenameCannotInjectHeaders(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') self::markTestSkipped('Windows filenames cannot contain these characters');
        $file = sys_get_temp_dir() . '/neili_' . bin2hex(random_bytes(8)) . "\"\r\nInjected: value.jpg";
        $this->files[] = $file;
        file_put_contents($file, 'safe-body');
        $http = new FakeHttpClient();
        $this->client($http)->sendDocument(1, new Media($file))->await();
        $body = $http->getLastRequestBody();
        self::assertStringNotContainsString("\r\nInjected:", $body);
        self::assertStringContainsString('filename="' . str_replace(['"', "\r", "\n"], '', basename($file)) . '"', $body);
        self::assertSame('safe-body', $this->parts($http)['document']);
    }
}
