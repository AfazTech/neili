<?php

declare(strict_types=1);

namespace Neili\Tests\Unit\Client;

use Amp\CompositeException;
use Neili\Client;
use Neili\Settings;
use Neili\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class FlushTest extends TestCase
{
    private function client(object $http): Client
    {
        return new Client((new Settings())->setAccessToken('123:abc')->setHttpClient($http));
    }

    public function testFlushDrainsRequestsAndCanBeReused(): void
    {
        $http = new FakeHttpClient();
        for ($i = 0; $i < 3; ++$i) {
            $http->pushJsonResponse(['ok' => true, 'result' => []]);
        }
        $client = $this->client($http);
        $first = $client->sendMessage(42, 'first');
        $client->sendMessage(42, 'second');
        $client->flush();
        self::assertTrue($first->isComplete());
        self::assertCount(2, $http->getRequests());
        $client->flush();
        $client->sendMessage(42, 'third');
        $client->flush();
        self::assertCount(3, $http->getRequests());
    }

    public function testFlushWaitsForAllFailuresAndSuccessesBeforeThrowing(): void
    {
        $http = new FakeHttpClient();
        $http->pushThrow(new \RuntimeException('first failure'));
        $http->pushJsonResponse(['ok' => true, 'result' => []]);
        $http->pushThrow(new \RuntimeException('second failure'));
        $client = $this->client($http);
        $client->sendMessage(42, 'one');
        $success = $client->sendMessage(42, 'two');
        $client->sendMessage(42, 'three');
        try {
            $client->flush();
            self::fail('Expected aggregated failures');
        } catch (CompositeException $error) {
            self::assertCount(2, $error->getReasons());
        }
        self::assertTrue($success->await()['ok']);
        self::assertCount(3, $http->getRequests());
        $client->flush();
    }

    public function testPreviouslyHandledFailureIsNotReportedAgain(): void
    {
        $http = new FakeHttpClient();
        $http->pushThrow(new \RuntimeException('handled'));
        $client = $this->client($http);
        try {
            $client->getMe()->await();
            self::fail('Expected request failure');
        } catch (\Neili\Exceptions\TransientException) {
        }
        $client->flush();
        self::assertCount(1, $http->getRequests());
    }

    public function testOperationsCreatedWhileFlushingAreDrained(): void
    {
        $http = new class {
            public ?Client $client = null;
            public int $calls = 0;
            public function request($request): \Amp\Http\Client\Response
            {
                if (++$this->calls === 1) {
                    $this->client->sendMessage(42, 'created during flush');
                }
                \Amp\delay(0.001);
                return new \Amp\Http\Client\Response('1.1', 200, null, [],
                    new \Amp\ByteStream\ReadableBuffer('{"ok":true,"result":[]}'), $request);
            }
        };
        $client = $http->client = $this->client($http);
        $client->getMe();
        $client->flush();
        self::assertSame(2, $http->calls);
    }

    public function testFailureCreatedDuringFlushIsRetainedUntilReported(): void
    {
        $http = new class {
            public ?Client $client = null;
            public int $calls = 0;
            public function request($request): \Amp\Http\Client\Response
            {
                if (++$this->calls === 2) {
                    throw new \RuntimeException('nested failure');
                }
                $this->client->sendMessage(42, 'fails before parent finishes');
                \Amp\delay(0.01);
                return new \Amp\Http\Client\Response('1.1', 200, null, [],
                    new \Amp\ByteStream\ReadableBuffer('{"ok":true,"result":[]}'), $request);
            }
        };
        $client = $http->client = $this->client($http);
        $parent = $client->getMe();
        try {
            $client->flush();
            self::fail('Expected nested request failure');
        } catch (CompositeException $error) {
            self::assertCount(1, $error->getReasons());
            self::assertStringContainsString('nested failure', $error->getReasons()[0]->getMessage());
        }
        self::assertTrue($parent->await()['ok']);
        $client->flush();
    }

    public function testFlushDoesNotDrainAnotherClient(): void
    {
        $a = new FakeHttpClient();
        $b = new FakeHttpClient();
        $a->pushJsonResponse(['ok' => true, 'result' => []]);
        $b->pushJsonResponse(['ok' => true, 'result' => []]);
        $first = $this->client($a);
        $second = $this->client($b);
        $second->getMe();
        $first->flush();
        self::assertCount(0, $b->getRequests());
        $second->flush();
    }
    public function testConcurrentFlushIsRejectedWithoutBreakingOriginalFlush(): void
    {
        $entered = new \Amp\DeferredFuture();
        $gate = new \Amp\DeferredFuture();
        $http = new class($entered, $gate) {
            public function __construct(private $entered, private $gate) {}
            public function request($request): \Amp\Http\Client\Response
            {
                $this->entered->complete();
                $this->gate->getFuture()->await();
                return new \Amp\Http\Client\Response('1.1', 200, null, [],
                    new \Amp\ByteStream\ReadableBuffer('{"ok":true,"result":true}'), $request);
            }
        };
        $client = $this->client($http);
        $request = $client->getMe();
        $flush = \Amp\async(fn () => $client->flush());
        try {
            $entered->getFuture()->await(new \Amp\TimeoutCancellation(2));
            try {
                $client->flush();
                self::fail('Expected concurrent flush rejection');
            } catch (\LogicException $error) {
                self::assertStringContainsString('already in progress', $error->getMessage());
            }
        } finally {
            $gate->complete();
            $flush->await(new \Amp\TimeoutCancellation(2));
        }
        self::assertTrue($request->await()['result']);
        $client->flush();
    }

    public function testNestedFlushIsRejectedAndOuterFlushCompletes(): void
    {
        $http = new class {
            public Client $client;
            public ?\Throwable $nestedError = null;
            public function request($request): \Amp\Http\Client\Response
            {
                try {
                    $this->client->flush();
                } catch (\LogicException $error) {
                    $this->nestedError = $error;
                }
                return new \Amp\Http\Client\Response('1.1', 200, null, [],
                    new \Amp\ByteStream\ReadableBuffer('{"ok":true,"result":true}'), $request);
            }
        };
        $client = $http->client = $this->client($http);
        $request = $client->getMe();
        $client->flush();
        self::assertInstanceOf(\LogicException::class, $http->nestedError);
        self::assertTrue($request->await()['result']);
        $client->flush();
    }

    public function testClientAcceptsNewRequestsAfterFailedFlush(): void
    {
        $http = new FakeHttpClient();
        $http->pushThrow(new \RuntimeException('first request failed'));
        $http->pushJsonResponse(['ok' => true, 'result' => 'recovered']);
        $client = $this->client($http);
        $client->getMe();
        try {
            $client->flush();
            self::fail('Expected failed flush');
        } catch (CompositeException $error) {
            self::assertCount(1, $error->getReasons());
        }
        $next = $client->getMe();
        $client->flush();
        self::assertSame('recovered', $next->await()['result']);
        self::assertCount(2, $http->getRequests());
    }

}
