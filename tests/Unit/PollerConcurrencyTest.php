<?php

declare(strict_types=1);

namespace Neili\Tests\Unit;

use Amp\DeferredFuture;
use Amp\Future;
use Amp\TimeoutCancellation;
use Neili\Client;
use Neili\Poller;
use Neili\Settings;
use PHPUnit\Framework\TestCase;
use function Amp\async;

final class PollerConcurrencyTest extends TestCase
{
    private function makeClient(?int $limit = 100): Client
    {
        return new class((new Settings())->setAccessToken('test')->setPollerMaxConcurrency($limit)) extends Client {
            public int $polls = 0;
            public array $batches = [];
            public ?Poller $poller = null;

            public function getUpdates(?int $offset = null, ?int $limit = null, ?int $timeout = null,
                ?array $allowedUpdates = null, ?array $extraParams = null): Future
            {
                ++$this->polls;
                if ($this->batches === []) {
                    $this->poller->stop();
                    return Future::complete(['ok' => true, 'result' => []]);
                }
                return Future::complete(['ok' => true, 'result' => array_shift($this->batches)]);
            }
        };
    }

    public function testDefaultLimitBackpressuresPollingAndDrainsHandlers(): void
    {
        $this->assertBounded(100);
    }

    public function testCustomLimitAlsoBoundsFibersWithinOneBatch(): void
    {
        $this->assertBounded(2);
    }

    private function assertBounded(int $limit): void
    {
        $client = $this->makeClient($limit);
        $poller = $client->poller = new Poller($client);
        $client->batches = [array_map(fn ($id) => ['update_id' => $id, 'message' => []], range(1, 100)),
            [['update_id' => 101, 'message' => []]]];
        $gate = new DeferredFuture();
        $full = new DeferredFuture();
        $active = $peak = $started = $finished = 0;
        $poller->onMessage(function () use ($gate, $full, $limit, &$active, &$peak, &$started, &$finished): void {
            ++$started;
            $peak = max($peak, ++$active);
            if ($started === $limit) {
                $full->complete();
            }
            $gate->getFuture()->await();
            --$active;
            ++$finished;
        });
        $run = async(fn () => $poller->start(false));
        $full->getFuture()->await(new TimeoutCancellation(2));
        self::assertSame($limit, $started);
        self::assertSame(1, $client->polls, 'Do not fetch more updates while at capacity');
        $gate->complete();
        $run->await(new TimeoutCancellation(2));
        self::assertSame($limit, $peak);
        self::assertSame(101, $finished, 'start must drain the final handlers before returning');
    }

    public function testReturnedHandlerFutureIsAwaitedAndItsErrorIsReported(): void
    {
        $client = $this->makeClient(1);
        $poller = $client->poller = new Poller($client);
        $client->batches = [[['update_id' => 1, 'message' => []]]];
        $errors = [];
        $order = [];
        $poller->onMessage(function () use (&$order): Future {
            return async(function () use (&$order): void {
                \Amp\delay(0.001);
                $order[] = 'future';
                throw new \RuntimeException('handler future failed');
            });
        });
        $poller->onUpdate(function () use (&$order): void { $order[] = 'global'; });
        $poller->onError(function ($error, $update, $type) use (&$errors): void {
            $errors[] = [$error->getMessage(), $update['update_id'], $type];
        });
        $poller->start(false);
        self::assertSame(['future', 'global'], $order);
        self::assertSame([['handler future failed', 1, 'message']], $errors);
    }

    public function testStopDrainsStartedHandlersWithoutStartingQueuedUpdates(): void
    {
        $client = $this->makeClient(1);
        $poller = $client->poller = new Poller($client);
        $client->batches = [[['update_id' => 1, 'message' => []], ['update_id' => 2, 'message' => []]]];
        $finished = [];
        $poller->onMessage(function ($update) use ($poller, &$finished): void {
            $poller->stop();
            \Amp\delay(0.001);
            $finished[] = $update['update_id'];
        });
        $poller->start(false);
        self::assertSame([1], $finished);
        self::assertSame(1, $client->polls);
    }
}
