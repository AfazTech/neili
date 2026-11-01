<?php

declare(strict_types=1);

namespace Neili\Tests\Unit\Client;

use Neili\Client;
use Neili\Settings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HandlesUpdatesTest extends TestCase
{
    private function decode(string $payload): array
    {
        $previous = $GLOBALS['argv'] ?? null;
        $GLOBALS['argv'] = ['webhook.php', base64_encode($payload)];
        try {
            return (new Client((new Settings())->setAccessToken('test')))->handleUpdate();
        } finally {
            if ($previous === null) {
                unset($GLOBALS['argv']);
            } else {
                $GLOBALS['argv'] = $previous;
            }
        }
    }

    public static function invalidUpdates(): iterable
    {
        foreach (['null', 'true', '42', '"string"', '[]', '{}', 'not json', '',
            '{"message":{}}', '{"update_id":"1"}', '{"update_id":1.5}', '{"update_id":-1}',
            '{"update_id":true}', '{"update_id":null}'] as $payload) {
            yield [$payload];
        }
    }

    #[DataProvider('invalidUpdates')]
    public function testInvalidUpdateIsRejectedWithMeaningfulException(string $payload): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->decode($payload);
    }

    public function testUnknownFutureUpdateTypeIsPreserved(): void
    {
        $update = ['update_id' => 0, 'future_update_type' => ['value' => 'hello']];
        self::assertSame($update, $this->decode(json_encode($update)));
    }

    public function testInvalidBase64IsRejected(): void
    {
        $previous = $GLOBALS['argv'];
        $GLOBALS['argv'] = ['webhook.php', '%%%'];
        try {
            $this->expectException(\UnexpectedValueException::class);
            $this->expectExceptionMessage('Invalid base64');
            (new Client((new Settings())->setAccessToken('test')))->handleUpdate();
        } finally {
            $GLOBALS['argv'] = $previous;
        }
    }
}
