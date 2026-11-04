<?php

declare(strict_types=1);

namespace Neili\Tests\Unit;

use Amp\Future;
use Neili\{Client, KeyboardBuilder, Media, Poller, Settings};
use Neili\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class PollerBacklogTest extends TestCase
{
    public function testDiscardSkipsBacklogLargerThanOneBatch(): void
    {
        $client = new class((new Settings())->setAccessToken('test')) extends Client {
            public array $queue;
            public array $calls = [];
            public Poller $poller;

            public function getUpdates(?int $offset = null, ?int $limit = null, ?int $timeout = null,
                ?array $allowedUpdates = null, ?array $extraParams = null): Future
            {
                $this->calls[] = [$offset, $limit, $timeout, $allowedUpdates];
                $updates = $offset !== null && $offset < 0
                    ? array_slice($this->queue, $offset)
                    : array_values(array_filter($this->queue, fn ($u) => $u['update_id'] >= ($offset ?? 0)));
                $updates = array_slice($updates, 0, $limit ?? 100);
                if (count($this->calls) === 1) {
                    $this->queue[] = ['update_id' => 202, 'message' => []];
                } elseif (count($this->calls) > 2) {
                    $this->poller->stop();
                }
                return Future::complete(['ok' => true, 'result' => $updates]);
            }
        };
        $client->queue = array_map(fn ($id) => ['update_id' => $id, 'message' => []], range(1, 201));
        $poller = $client->poller = new Poller($client);
        $handled = [];
        $poller->onMessage(function ($update) use (&$handled): void { $handled[] = $update['update_id']; });
        $poller->start(true);
        self::assertSame([202], $handled);
        self::assertSame([-1, 1, 0, $client->getSettings()->getPollerAllowedUpdates()], $client->calls[0]);
        self::assertSame(202, $client->calls[1][0]);
    }
}
