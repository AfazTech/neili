<?php

declare(strict_types=1);

namespace Neili\Tests\Unit;

use Neili\Client;
use Neili\Exceptions\PermanentException;
use Neili\Exceptions\RateLimitException;
use Neili\Exceptions\TransientException;
use Neili\Poller;
use Neili\Settings;
use Neili\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;
use function Amp\async;
use function Amp\delay;

/**
 * Behavioral tests for the polling loop's error handling after the
 * removal of Neili's internal logger.
 *
 * The suite verifies that:
 *   - Recoverable errors (handler exception, transient HTTP failure,
 *     rate-limit, reconnect failure, transport error) reach the consumer
 *     through Poller::onError() while the loop keeps running.
 *   - Fatal errors (PermanentException) propagate to the caller and
 *     are NOT routed through onError(), both in the main loop and in
 *     the discardOldUpdates pre-loop fetch.
 *   - A misbehaving onError callback does not kill or hang the poller.
 *
 * Backoff base/max are set to zero in every test so retries do not slow
 * the suite. Amp v3's delay() takes seconds, so any explicit wait here
 * uses fractional seconds.
 */
final class PollerTest extends TestCase
{
    /**
     * Build a Settings/Client/Poller trio wired to the given FakeHttpClient.
     */
    private function makePoller(FakeHttpClient $http): Poller
    {
        $settings = (new Settings())
            ->setAccessToken('123:abc')
            ->setApiUrl('https://api.telegram.org/bot')
            ->setHttpClient($http)
            ->setPollerTimeout(1)
            ->setPollerBackoffBase(0)
            ->setPollerMaxBackoff(0);

        return new Poller(new Client($settings));
    }

    public function testHandlerExceptionIsReportedToOnError(): void
    {
        $http = new FakeHttpClient();
        $http->pushJsonResponse([
            'ok' => true,
            'result' => [
                ['update_id' => 1, 'message' => ['text' => 'boom']],
            ],
        ]);
        // A second successful response gives the async dispatch a full
        // event-loop tick to run the handler before the loop re-enters.
        $http->pushJsonResponse(['ok' => true, 'result' => []]);

        $poller = $this->makePoller($http);

        $errors = [];
        $poller->onError(function (\Throwable $e, array $update, string $type) use (&$errors, $poller) {
            $errors[] = [$e, $update, $type];
            $poller->stop();
        });
        $poller->onMessage(function (): void {
            throw new \RuntimeException('handler blew up');
        });

        $poller->start(discardOldUpdates: false);

        self::assertCount(1, $errors);
        self::assertSame('handler blew up', $errors[0][0]->getMessage());
        self::assertSame('message', $errors[0][2]);
        self::assertSame('boom', $errors[0][1]['message']['text']);
    }

    public function testTransientExceptionIsReportedToOnErrorAndPollerContinues(): void
    {
        $http = new FakeHttpClient();
        $http->pushResponse(503, 'Service Unavailable');

        $poller = $this->makePoller($http);

        $errors = [];
        $poller->onError(function (\Throwable $e, array $update, string $type) use (&$errors, $poller) {
            $errors[] = [$e, $update, $type];
            $poller->stop();
        });

        $poller->start(discardOldUpdates: false);

        self::assertCount(1, $errors);
        self::assertInstanceOf(TransientException::class, $errors[0][0]);
        self::assertSame('poller', $errors[0][2]);
        self::assertSame([], $errors[0][1]);
        self::assertFalse($poller->isRunning());
    }

    public function testRateLimitExceptionIsReportedToOnError(): void
    {
        $http = new FakeHttpClient();
        $http->pushJsonResponse([
            'ok' => false,
            'error_code' => 429,
            'description' => 'Too Many Requests',
            'parameters' => ['retry_after' => 1],
        ]);

        $poller = $this->makePoller($http);

        $errors = [];
        $poller->onError(function (\Throwable $e) use (&$errors, $poller) {
            $errors[] = $e;
            $poller->stop();
        });

        // Run the poller in a detached fiber: the poller will suspend on
        // Amp\delay() for the full retry_after window after onError fires,
        // so we assert on the side effects instead of awaiting start().
        async(fn () => $poller->start(discardOldUpdates: false));

        // 50 ms is far below the 1 s retry_after; the onError callback
        // fires before the delay begins, so this window is more than enough.
        delay(0.05);

        self::assertCount(1, $errors);
        self::assertInstanceOf(RateLimitException::class, $errors[0]);
        self::assertSame(1, $errors[0]->getRetryAfter());
        self::assertSame(['retry_after' => 1], $errors[0]->getParameters());
        self::assertFalse($poller->isRunning());

        // Let the detached fiber drain its remaining retry_after delay so
        // it does not bleed into the next test's event loop.
        delay(1.1);
    }

    public function testPermanentExceptionPropagatesAndBypassesOnError(): void
    {
        $http = new FakeHttpClient();
        $http->pushJsonResponse([
            'ok' => false,
            'error_code' => 401,
            'description' => 'Unauthorized',
        ]);

        $poller = $this->makePoller($http);

        $reported = [];
        $poller->onError(function (\Throwable $e) use (&$reported) {
            $reported[] = $e;
        });

        try {
            $poller->start(discardOldUpdates: false);
            self::fail('Expected PermanentException to propagate out of Poller::start()');
        } catch (PermanentException $e) {
            self::assertSame('Unauthorized', $e->getMessage());
            self::assertSame(401, $e->getErrorCode());
        }

        self::assertSame([], $reported, 'onError must not be called for PermanentException');
        self::assertFalse($poller->isRunning());
    }

    public function testPermanentExceptionDuringDiscardPropagatesAndBypassesOnError(): void
    {
        $http = new FakeHttpClient();
        // The very first request is the discard pre-loop fetch. It must
        // receive the fatal response so the poller aborts before entering
        // the main loop.
        $http->pushJsonResponse([
            'ok' => false,
            'error_code' => 401,
            'description' => 'Unauthorized',
        ]);

        $poller = $this->makePoller($http);

        $reported = [];
        $poller->onError(function (\Throwable $e) use (&$reported) {
            $reported[] = $e;
        });

        try {
            $poller->start(discardOldUpdates: true);
            self::fail('Expected PermanentException to propagate out of Poller::start() during discardOldUpdates');
        } catch (PermanentException $e) {
            self::assertSame('Unauthorized', $e->getMessage());
            self::assertSame(401, $e->getErrorCode());
        }

        self::assertSame(
            [],
            $reported,
            'onError must not be called for PermanentException raised during discardOldUpdates'
        );
        self::assertFalse($poller->isRunning());
    }

    public function testTransientErrorDuringDiscardIsReportedAndLoopContinues(): void
    {
        $http = new FakeHttpClient();
        // First response feeds the discard pre-loop fetch and fails
        // transiently; the poller must report it through onError and then
        // enter the main loop, where a successful response lets us stop.
        $http->pushResponse(503, 'Service Unavailable');
        $http->pushJsonResponse(['ok' => true, 'result' => []]);

        $poller = $this->makePoller($http);

        $errors = [];
        $poller->onError(function (\Throwable $e, array $update, string $type) use (&$errors, $poller) {
            $errors[] = [$e, $update, $type];
            $poller->stop();
        });

        $poller->start(discardOldUpdates: true);

        self::assertCount(1, $errors);
        self::assertInstanceOf(TransientException::class, $errors[0][0]);
        self::assertSame('poller', $errors[0][2]);
        self::assertSame([], $errors[0][1]);
        self::assertFalse($poller->isRunning());
    }

    public function testTransportErrorIsTranslatedAndReportedToOnError(): void
    {
        $http = new FakeHttpClient();
        // Simulate a low-level transport failure (DNS, TCP, TLS, ...).
        $http->pushThrow(new \RuntimeException('connection refused'));

        $poller = $this->makePoller($http);

        $errors = [];
        $poller->onError(function (\Throwable $e, array $update, string $type) use (&$errors, $poller) {
            $errors[] = [$e, $update, $type];
            $poller->stop();
        });

        $poller->start(discardOldUpdates: false);

        self::assertCount(1, $errors);
        self::assertInstanceOf(TransientException::class, $errors[0][0]);
        self::assertStringContainsString('Transport error', $errors[0][0]->getMessage());
        self::assertStringContainsString('connection refused', $errors[0][0]->getMessage());
    }

    public function testReconnectFailureIsReportedToOnErrorAndPollerContinues(): void
    {
        $http = new FakeHttpClient();
        // Three consecutive transient failures trigger the reconnect threshold.
        $http->pushResponse(503, 'Service Unavailable');
        $http->pushResponse(503, 'Service Unavailable');
        $http->pushResponse(503, 'Service Unavailable');

        $reconnectException = new \RuntimeException('reconnect blew up');

        $settings = (new Settings())
            ->setAccessToken('123:abc')
            ->setApiUrl('https://api.telegram.org/bot')
            ->setHttpClient($http)
            ->setPollerTimeout(1)
            ->setPollerBackoffBase(0)
            ->setPollerMaxBackoff(0);

        $client = new class($settings) extends Client {
            public int $reconnectCount = 0;
            public ?\Throwable $reconnectError = null;

            public function reconnect(): void
            {
                $this->reconnectCount++;
                if ($this->reconnectError !== null) {
                    throw $this->reconnectError;
                }
                parent::reconnect();
            }
        };
        $client->reconnectError = $reconnectException;

        $poller = new Poller($client);

        $errors = [];
        $poller->onError(function (\Throwable $e) use (&$errors, $poller) {
            $errors[] = $e;
            if (count($errors) >= 4) {
                $poller->stop();
            }
        });

        $poller->start(discardOldUpdates: false);

        self::assertGreaterThanOrEqual(1, $client->reconnectCount);
        self::assertCount(4, $errors);
        self::assertInstanceOf(TransientException::class, $errors[0]);
        self::assertInstanceOf(TransientException::class, $errors[1]);
        self::assertInstanceOf(TransientException::class, $errors[2]);
        self::assertSame('reconnect blew up', $errors[3]->getMessage());
    }

    public function testThrowingOnErrorCallbackDoesNotBreakPoller(): void
    {
        $http = new FakeHttpClient();
        $http->pushResponse(503, 'Service Unavailable');

        $poller = $this->makePoller($http);

        $calls = 0;
        $poller->onError(function () use (&$calls, $poller) {
            $calls++;
            $poller->stop();
            throw new \RuntimeException('onError callback is broken');
        });

        $poller->start(discardOldUpdates: false);

        self::assertSame(1, $calls);
    }
}
