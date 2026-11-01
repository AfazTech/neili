<?php

declare(strict_types=1);

namespace Neili;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Connection\DefaultConnectionFactory;
use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;
use Amp\Future;
use Neili\Client\Concerns\HandlesBusiness;
use Neili\Client\Concerns\HandlesEphemeralMessages;
use Neili\Client\Concerns\HandlesGames;
use Neili\Client\Concerns\HandlesGifts;
use Neili\Client\Concerns\HandlesInlineQueries;
use Neili\Client\Concerns\HandlesPassport;
use Neili\Client\Concerns\HandlesPayments;
use Neili\Client\Concerns\HandlesRichMessages;
use Neili\Client\Concerns\HandlesStories;
use Neili\Client\Concerns\HandlesSuggestedPosts;
use Neili\Client\Concerns\HandlesUpdates;
use Neili\Client\Concerns\HandlesVerification;
use Neili\Client\Concerns\HandlesWebhooks;
use Neili\Client\Concerns\MakesHttpRequests;
use Neili\Client\Concerns\ManagesBotProfile;
use Neili\Client\Concerns\ManagesChatMembers;
use Neili\Client\Concerns\ManagesChats;
use Neili\Client\Concerns\ManagesFiles;
use Neili\Client\Concerns\ManagesForumTopics;
use Neili\Client\Concerns\ManagesManagedBots;
use Neili\Client\Concerns\ManagesStickers;
use Neili\Client\Concerns\SendsMedia;
use Neili\Client\Concerns\SendsMessages;

class Client
{
    use MakesHttpRequests;
    use SendsMessages;
    use SendsMedia;
    use ManagesChats;
    use ManagesChatMembers;
    use ManagesStickers;
    use ManagesForumTopics;
    use ManagesBotProfile;
    use HandlesPayments;
    use HandlesGames;
    use HandlesInlineQueries;
    use HandlesUpdates;
    use HandlesWebhooks;
    use ManagesFiles;
    use HandlesStories;
    use HandlesBusiness;
    use HandlesGifts;
    use HandlesVerification;
    use ManagesManagedBots;
    use HandlesSuggestedPosts;
    use HandlesEphemeralMessages;
    use HandlesRichMessages;
    use HandlesPassport;

    /** @var array<int, Future> Operations that have not finished yet. */
    private array $pendingOperations = [];

    private int $nextOperationId = 0;

    private bool $flushing = false;

    /** @var array<int, Future> Also retains operations created during a flush. */
    private array $flushQueue = [];

    /**
     * Schedule an operation without changing the Future's error handling.
     * Completed operations are released even when flush() is never called.
     */
    private function asyncOperation(\Closure $operation): Future
    {
        $id = $this->nextOperationId++;

        $future = $this->pendingOperations[$id] = \Amp\async(function () use ($id, $operation) {
            try {
                return $operation();
            } finally {
                unset($this->pendingOperations[$id]);
            }
        });

        if ($this->flushing) {
            $this->flushQueue[$id] = $future;
        }

        return $future;
    }

    /**
     * Wait for this client's pending operations, including operations created
     * while waiting. Does not wait for unrelated event-loop work or callbacks
     * attached by callers. Already completed operations are not replayed.
     *
     * @throws \Amp\CompositeException After draining, if pending operations failed.
     * @throws \LogicException If another flush is already in progress.
     */
    public function flush(): void
    {
        if ($this->flushing) {
            throw new \LogicException('A flush is already in progress for this client');
        }

        $this->flushing = true;
        $errors = [];
        $this->flushQueue = $this->pendingOperations;

        try {
            while ($this->flushQueue !== []) {
                $batch = $this->flushQueue;
                $this->flushQueue = [];
                [$batchErrors] = \Amp\Future\awaitAll($batch);
                foreach ($batchErrors as $error) {
                    $errors[spl_object_id($error)] = $error;
                }
            }
        } finally {
            $this->flushing = false;
            $this->flushQueue = [];
        }

        if ($errors !== []) {
            throw new \Amp\CompositeException(array_values($errors));
        }
    }

    /**
     * Telegram bot settings (token, API URL, etc)
     */
    private Settings $settings;

    /**
     * HTTP client for async requests.
     *
     * Typed as object rather than Amp\Http\Client\HttpClient because that
     * symbol has been observed as a class in some amphp/http-client builds.
     * Whatever value the builder or the caller provides just needs a
     * compatible request() method.
     */
    private object $httpClient;

    /**
     * Extra grace period (seconds) added on top of a getUpdates long-poll
     * timeout so that the transport does not abort a still-valid long poll.
     */
    private const LONG_POLL_GRACE_SECONDS = 10;

    /**
     * Constructor
     * Initializes the HTTP client and stores settings.
     *
     * If Settings carries an injected HTTP client (e.g. one built with a
     * proxy, custom connector, or interceptors), that instance is used;
     * otherwise the default builder is invoked. In amphp/http-client v5 the
     * transfer / connect timeouts are NOT configurable on HttpClientBuilder,
     * so they are applied per-Request by request() / requestWithFile().
     */
    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
        $this->httpClient = $this->buildHttpClient();
    }

    /**
     * Get current settings
     */
    public function getSettings(): Settings
    {
        return $this->settings;
    }

    /**
     * Replace the HTTP client at runtime.
     *
     * Provided so callers can rotate transport-level configuration (e.g.
     * a failing proxy) without rebuilding the whole Neili client.
     */
    public function setHttpClient(object $client): void
    {
        $this->httpClient = $client;
    }

    /**
     * Rebuild the underlying HTTP client.
     *
     * Called by the Poller after a run of consecutive transient failures so
     * that stale connections / a broken pool are discarded and a fresh
     * transport is used. This is a recovery mechanism, not a reaction to
     * every individual exception.
     */
    public function reconnect(): void
    {
        $this->httpClient = $this->buildHttpClient();
    }

    private function buildHttpClient(): object
    {
        // A caller-supplied transport owns its TLS configuration.
        if (($injected = $this->settings->getHttpClient()) !== null) {
            return $injected;
        }

        $tls = new ClientTlsContext();
        if (!$this->settings->isApiVerifySSL()) {
            $tls = $tls->withoutPeerVerification();
        }
        $context = (new ConnectContext())->withTlsContext($tls);
        $pool = new UnlimitedConnectionPool(new DefaultConnectionFactory(null, $context));

        return (new HttpClientBuilder())->usingPool($pool)->build();
    }

    /**
     * Magic method for dynamically calling Telegram API methods.
     * Converts calls like $client->sendMessage(...) to request('sendMessage', [...])
     */
    public function __call($method, $arguments): Future
    {
        return $this->request($method, $arguments[0] ?? []);
    }
}
