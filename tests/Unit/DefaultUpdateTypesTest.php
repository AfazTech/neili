<?php

declare(strict_types=1);

namespace Neili\Tests\Unit;

use Amp\Future;
use Neili\{Client, KeyboardBuilder, Media, Poller, Settings};
use Neili\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class DefaultUpdateTypesTest extends TestCase
{
    public function testDefaultUpdateTypesIncludeBothNewHandlers(): void
    {
        $types = (new Settings())->getPollerAllowedUpdates();
        self::assertContains('subscription', $types);
        self::assertContains('stopped_message_generation', $types);
    }
}
