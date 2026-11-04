<?php

declare(strict_types=1);

namespace Neili\Tests\Unit;

use Amp\Future;
use Neili\Client;
use Neili\Exceptions\TransientException;
use Neili\Poller;
use Neili\Settings;
use PHPUnit\Framework\TestCase;

final class PollerLifecycleTest extends TestCase
{
    private function client(): Client
    {
        return new class((new Settings())->setAccessToken('test')->setPollerTimeout(7)
            ->setPollerBackoffBase(0)->setPollerMaxBackoff(0)) extends Client {
            public array $responses = [];
            public array $calls = [];
            public array $reconnectCalls = [];
            public Poller $poller;
            public ?\Closure $onPoll = null;

            public function getUpdates(?int $offset = null, ?int $limit = null, ?int $timeout = null,
                ?array $allowedUpdates = null, ?array $extraParams = null): Future
            {
                $this->calls[] = compact('offset', 'limit', 'timeout', 'allowedUpdates');
                if ($this->onPoll !== null) ($this->onPoll)();
                if ($this->responses === []) {
                    $this->poller->stop();
                    return Future::complete(['ok' => true, 'result' => []]);
                }
                $next = array_shift($this->responses);
                return $next instanceof \Throwable ? Future::error($next)
                    : Future::complete(['ok' => true, 'result' => $next]);
            }

            public function reconnect(): void
            {
                $this->reconnectCalls[] = count($this->calls);
            }
        };
    }

    public function testOffsetAdvancesAcrossBatchesAndAllowedUpdatesAreForwarded(): void
    {
        $client = $this->client();
        $client->getSettings()->setPollerAllowedUpdates(['callback_query', 'message']);
        $poller = $client->poller = new Poller($client);
        $client->responses = [[['update_id' => 10, 'message' => []], ['update_id' => 12, 'message' => []]],
            [['update_id' => 20, 'message' => []]]];
        $seen = [];
        $poller->onMessage(function ($update) use (&$seen): void { $seen[] = $update['update_id']; });
        $poller->start(false);
        self::assertSame([0, 13, 21], array_column($client->calls, 'offset'));
        self::assertSame([10, 12, 20], $seen);
        foreach ($client->calls as $call) {
            self::assertSame(['callback_query', 'message'], $call['allowedUpdates']);
            self::assertSame(7, $call['timeout']);
        }
    }

    public function testDiscardUsesShortPollAndDoesNotDispatchDiscardedUpdates(): void
    {
        $client = $this->client();
        $poller = $client->poller = new Poller($client);
        $client->responses = [[['update_id' => 4, 'message' => []], ['update_id' => 8, 'message' => []]],
            [['update_id' => 9, 'message' => []]]];
        $seen = [];
        $poller->onMessage(function ($update) use (&$seen): void { $seen[] = $update['update_id']; });
        $poller->start(true);
        self::assertSame(-1, $client->calls[0]['offset']);
        self::assertSame(1, $client->calls[0]['limit']);
        self::assertSame(0, $client->calls[0]['timeout']);
        self::assertSame([9], $seen);
        self::assertSame([9, 10], array_column(array_slice($client->calls, 1), 'offset'));
    }

    public function testEmptyAllowedUpdatesAreNotReplacedByDefaults(): void
    {
        $client = $this->client();
        $client->getSettings()->setPollerAllowedUpdates([]);
        $poller = $client->poller = new Poller($client);
        $poller->start(false);
        self::assertSame([], $client->calls[0]['allowedUpdates']);
    }

    public function testSecondStartIsRejectedWhileRunning(): void
    {
        $client = $this->client();
        $poller = $client->poller = new Poller($client);
        $rejected = false;
        $client->onPoll = function () use ($poller, &$rejected): void {
            try {
                $poller->start(false);
            } catch (\RuntimeException $error) {
                $rejected = $error->getMessage() === 'Poller already running';
            }
        };
        $poller->start(false);
        self::assertTrue($rejected);
        self::assertFalse($poller->isRunning());
    }

    public function testStartIsRejectedWhileFinalHandlersDrain(): void
    {
        $client = $this->client();
        $poller = $client->poller = new Poller($client);
        $client->responses = [[['update_id' => 1, 'message' => []]]];
        $entered = new \Amp\DeferredFuture();
        $gate = new \Amp\DeferredFuture();
        $poller->onMessage(function () use ($entered, $gate): void {
            $entered->complete();
            $gate->getFuture()->await();
        });
        $run = \Amp\async(fn () => $poller->start(false));
        try {
            $entered->getFuture()->await(new \Amp\TimeoutCancellation(2));
            \Amp\delay(0); // Let the main loop enter its final handler-drain phase.
            self::assertFalse($poller->isRunning());
            try {
                $poller->start(false);
                self::fail('Expected restart rejection while draining');
            } catch (\RuntimeException $error) {
                self::assertSame('Poller already running', $error->getMessage());
            }
        } finally {
            $gate->complete();
            $run->await(new \Amp\TimeoutCancellation(2));
        }
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testRestartImmediatelyAfterStopInsideHandlerIsRejected(): void
    {
        $client = $this->client();
        $poller = $client->poller = new Poller($client);
        $client->responses = [[['update_id' => 1, 'message' => []]]];
        $rejected = false;
        $poller->onMessage(function () use ($poller, &$rejected): void {
            $poller->stop();
            try {
                $poller->start(false);
            } catch (\RuntimeException $error) {
                $rejected = $error->getMessage() === 'Poller already running';
            }
        });
        $run = \Amp\async(fn () => $poller->start(false));
        try {
            $run->await(new \Amp\TimeoutCancellation(1));
        } catch (\Amp\CancelledException) {
            self::fail('Restart after stop was accepted before draining, causing the handler to wait on itself');
        } catch (\Error $error) {
            if (!str_contains($error->getMessage(), 'Event loop terminated without resuming')) {
                throw $error;
            }
            self::fail('Restart after stop must be rejected; instead the poller deadlocked while draining its own handler');
        }
        self::assertTrue($rejected, 'An active handler must not restart its own poller');
    }

    public function testPollerCanRestartAfterPreviousRunReturns(): void
    {
        $client = $this->client();
        $poller = $client->poller = new Poller($client);
        $seen = [];
        $poller->onMessage(function ($update) use (&$seen): void { $seen[] = $update['update_id']; });
        $client->responses = [[['update_id' => 1, 'message' => []]]];
        $poller->start(false);
        $client->responses = [[['update_id' => 2, 'message' => []]]];
        $poller->start(false);
        self::assertSame([1, 2], $seen);
        self::assertFalse($poller->isRunning());
    }

    public function testFatalErrorsReleaseRunGuardDuringStartupAndPolling(): void
    {
        foreach ([true, false] as $discard) {
            $client = $this->client();
            $poller = $client->poller = new Poller($client);
            $failure = new \Neili\Exceptions\PermanentException('fatal test error');
            $client->responses = [$failure];
            try {
                $poller->start($discard);
                self::fail('Expected fatal polling error');
            } catch (\Neili\Exceptions\PermanentException $error) {
                self::assertSame($failure, $error);
            }
            $poller->start(false);
            self::assertFalse($poller->isRunning());
        }
    }

    public function testSuccessResetsConsecutiveReconnectFailureCounter(): void
    {
        $client = $this->client();
        $poller = $client->poller = new Poller($client);
        $client->responses = [new TransientException('one'), new TransientException('two'), [],
            new TransientException('one again'), new TransientException('two again'),
            new TransientException('three again'), []];
        $errors = [];
        $poller->onError(function ($error) use (&$errors): void { $errors[] = $error; });
        $poller->start(false);
        self::assertCount(5, $errors);
        self::assertSame([6], $client->reconnectCalls);
        self::assertFalse($poller->isRunning());
    }
}
