<?php

declare(strict_types=1);

namespace Neili\Tests\Unit;

use Amp\Future;
use Neili\{Client, KeyboardBuilder, Media, Poller, Settings};
use Neili\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class KeyboardMixingTest extends TestCase
{
    public function testAllInlineEntryPointsRejectExistingReplyRows(): void
    {
        $actions = [
            fn ($b) => $b->inlineRow(['B' => 'cb']),
            fn ($b) => $b->inlineCallbackRow(['B' => 'cb']),
            fn ($b) => $b->inlineUrlRow(['B' => 'https://example.com']),
            fn ($b) => $b->inlineButtonRow([['text' => 'B', 'callback_data' => 'cb']]),
            fn ($b) => $b->inlineButton(['text' => 'B', 'callback_data' => 'cb']),
            fn ($b) => $b->inline(),
        ];
        foreach ($actions as $action) {
            $builder = (new KeyboardBuilder())->row('A');
            try {
                $action($builder);
                self::fail('Mixed keyboard must be rejected');
            } catch (\RuntimeException $error) {
                self::assertStringContainsString('Cannot mix', $error->getMessage());
            }
            self::assertSame('A', $builder->build()['keyboard'][0][0]['text']);
            $action($builder->clear());
            self::assertArrayHasKey('inline_keyboard', $builder->build());
        }
    }
}
