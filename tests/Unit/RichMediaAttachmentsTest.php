<?php

declare(strict_types=1);

namespace Neili\Tests\Unit;

use Amp\Future;
use Neili\{Client, KeyboardBuilder, Media, Poller, Settings};
use Neili\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class RichMediaAttachmentsTest extends TestCase
{
    public function testRichMediaReferencesMatchCollectedAttachments(): void
    {
        $client = new Client((new Settings())->setAccessToken('test'));
        $method = new \ReflectionMethod($client, 'extractRichMessageAttachments');
        $attachments = [];
        $input = ['media' => [
            ['id' => 'video', 'media' => ['type' => 'video', 'media' => new Media('video.mp4'),
                'thumbnail' => new Media('thumb.jpg'), 'cover' => new Media('cover.jpg')]],
            ['id' => 'photo', 'media' => ['type' => 'photo', 'media' => new Media('photo.jpg')]],
        ]];
        $args = [$input, &$attachments];
        $normalized = $method->invokeArgs($client, $args);
        $references = [];
        foreach ($normalized['media'] as $item) {
            foreach (['media', 'thumbnail', 'cover'] as $field) {
                if (isset($item['media'][$field])) {
                    $key = substr($item['media'][$field], strlen('attach://'));
                    self::assertArrayHasKey($key, $attachments);
                    $references[] = $key;
                }
            }
        }
        self::assertCount(4, array_unique($references));
    }
}
