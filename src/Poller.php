<?php

/**
 * @version 2.2.10
 * @author Abolfazl Majidi (Afaz)
 * @package neili
 * @license https://opensource.org/licenses/MIT
 * @link https://github.com/AfazTech/neili
 */

declare(strict_types=1);

namespace Neili;

use Amp\Future;
use function Amp\async;
use function Amp\delay;
use Amp\Sync\LocalSemaphore;
use Neili\Exceptions\PermanentException;
use Neili\Exceptions\RateLimitException;
use Neili\Exceptions\TransientException;

class Poller
{
    private Client $client;
    private array $handlers = [];
    private $updateHandler = null; // backward compatible single update callback
    private int $offset = 0; // last processed update ID
    private bool $running = false; // poller active state
    private ?Future $mainFuture = null; // main async loop future
    private string $lockFile = 'neili.lock'; // optional lock file for single instance
    private Logger $logger;
    private ?LocalSemaphore $semaphore = null; // concurrency control semaphore

    /**
     * Number of consecutive transient failures after which the underlying
     * HTTP client is rebuilt. Keeps reconnect a recovery mechanism rather
     * than a reaction to every individual exception.
     */
    private const RECONNECT_AFTER_CONSECUTIVE_FAILURES = 3;

    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->logger = $this->client->getSettings()->getLogger();

        $maxConcurrency = $this->client->getSettings()->getPollerMaxConcurrency();
        $this->semaphore = $maxConcurrency ? new LocalSemaphore($maxConcurrency) : null;
    }

    // Set a global update callback
    public function onUpdate(callable $callback): void
    {
        $this->updateHandler = $callback;
    }

    // Register callback handlers for specific Telegram update types
    public function onMessage(callable $callback): void { $this->handlers['message'][] = $callback; }
    public function onEditedMessage(callable $callback): void { $this->handlers['edited_message'][] = $callback; }
    public function onMessageReaction(callable $callback): void { $this->handlers['message_reaction'][] = $callback; }
    public function onMessageReactionCount(callable $callback): void { $this->handlers['message_reaction_count'][] = $callback; }
    public function onChatBoost(callable $callback): void { $this->handlers['chat_boost'][] = $callback; }
    public function onRemovedChatBoost(callable $callback): void { $this->handlers['removed_chat_boost'][] = $callback; }
    public function onChannelPost(callable $callback): void { $this->handlers['channel_post'][] = $callback; }
    public function onEditedChannelPost(callable $callback): void { $this->handlers['edited_channel_post'][] = $callback; }
    public function onInlineQuery(callable $callback): void { $this->handlers['inline_query'][] = $callback; }
    public function onChosenInlineResult(callable $callback): void { $this->handlers['chosen_inline_result'][] = $callback; }
    public function onCallbackQuery(callable $callback): void { $this->handlers['callback_query'][] = $callback; }
    public function onShippingQuery(callable $callback): void { $this->handlers['shipping_query'][] = $callback; }
    public function onPreCheckoutQuery(callable $callback): void { $this->handlers['pre_checkout_query'][] = $callback; }
    public function onPoll(callable $callback): void { $this->handlers['poll'][] = $callback; }
    public function onPollAnswer(callable $callback): void { $this->handlers['poll_answer'][] = $callback; }
    public function onMyChatMember(callable $callback): void { $this->handlers['my_chat_member'][] = $callback; }
    public function onChatMember(callable $callback): void { $this->handlers['chat_member'][] = $callback; }
    public function onChatJoinRequest(callable $callback): void { $this->handlers['chat_join_request'][] = $callback; }
    public function onBusinessMessage(callable $callback): void { $this->handlers['business_message'][] = $callback; }
    public function onEditedBusinessMessage(callable $callback): void { $this->handlers['edited_business_message'][] = $callback; }
    public function onDeletedBusinessMessage(callable $callback): void { $this->handlers['deleted_business_message'][] = $callback; }
    public function onBusinessConnection(callable $callback): void { $this->handlers['business_connection'][] = $callback; }

    // Determine the type of incoming update based on registered handlers
    private function detectType(array $update): string
    {
        foreach (array_keys($this->handlers) as $type) {
            if (isset($update[$type])) return $type;
        }
        return 'unknown';
    }

    /**
     * Compute the exponential backoff delay (in seconds) with a small jitter,
     * capped at the configured maximum. Positive jitter prevents multiple
     * bots from retrying in lock-step after a shared outage.
     */
    private function computeBackoff(int $failureCount, int $backoffBase, int $maxBackoff): float
    {
        $exponent = max(0, min($failureCount - 1, 6));
        $base = $backoffBase * (2 ** $exponent);
        $base = min($base, $maxBackoff);
        $jitter = random_int(0, 1000) / 1000.0;
        return (float) ($base + $jitter);
    }

    // Start polling loop with optional discarding of old updates
    public function start(bool $discardOldUpdates = true): void
    {
        if ($this->running) throw new \RuntimeException('Poller already running');

        // Ensure background execution and unlimited script runtime
        if (function_exists('ignore_user_abort')) ignore_user_abort(true);
        if (function_exists('set_time_limit')) set_time_limit(0);
        if (function_exists('ini_set')) @ini_set('max_execution_time', '0');

        // Send headers and flush if not running in CLI
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            header('Connection: close');
            header('Content-Type: text/html');
            echo "Poller started in background";
            flush();
            if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
            if (function_exists('litespeed_finish_request')) litespeed_finish_request();
        }

        $this->running = true;

        $settings = $this->client->getSettings();
        $timeout = $settings->getPollerTimeout();
        $backoffBase = $settings->getPollerBackoffBase();
        $maxBackoff = $settings->getPollerMaxBackoff();

        // Optionally discard old updates to start fresh.
        // IMPORTANT: use a short-poll fetch (timeout=0) here, not the long-poll $timeout,
        // otherwise a real incoming message arriving right at startup could be consumed
        // by this throwaway request and silently lost.
        if ($discardOldUpdates) {
            try {
                $latest = $this->client->getUpdates(null, null, 0)->await();
                $result = $latest['result'] ?? [];
                if ($result) $this->offset = (int) end($result)['update_id'] + 1;
            } catch (\Throwable $e) {
                $this->logger->warning("Discard old updates failed: ".$e->getMessage());
            }
        }

        // Main asynchronous polling loop.
        //
        // Error handling policy (see project docs "Error Handling"):
        //   - TransientException: network/5xx - log, backoff, optionally
        //     reconnect the HTTP client, then continue polling.
        //   - RateLimitException (429): respect retry_after and continue.
        //   - PermanentException: fatal (e.g. invalid token) - stop and rethrow.
        //   - Any other Throwable: treat as transient (safe default).
        // A successful getUpdates resets both backoff counters.
        $this->mainFuture = async(function () use ($timeout, $backoffBase, $maxBackoff) {
            $failureCount = 0;
            $connectionFailureCount = 0;

            while ($this->running) {
                try {
                    $response = $this->client->getUpdates(
                        $this->offset,
                        null,
                        $timeout,
                        array_keys($this->handlers)
                    )->await();

                    // Success: reset backoff / reconnect counters.
                    $failureCount = 0;
                    $connectionFailureCount = 0;

                    $updates = $response['result'] ?? [];

                    foreach ($updates as $update) {
                        if (!is_array($update)) continue;
                        $this->offset = (int) ($update['update_id'] ?? $this->offset) + 1;

                        async(function () use ($update) {
                            $lock = $this->semaphore?->acquire();
                            try {
                                $type = $this->detectType($update);
                                foreach ($this->handlers[$type] ?? [] as $handler) {
                                    try { $handler($update); }
                                    catch (\Throwable $e) { $this->logger->error("Handler error for {$type}: ".$e->getMessage()); }
                                }
                                if ($this->updateHandler !== null) {
                                    try { ($this->updateHandler)($update); }
                                    catch (\Throwable $e) { $this->logger->error("onUpdate handler error: ".$e->getMessage()); }
                                }
                            } finally { $lock?->release(); }
                        });
                    }
                } catch (RateLimitException $e) {
                    // Telegram explicitly told us how long to wait. Prefer that
                    // value over the exponential backoff.
                    $retryAfter = max(1, $e->getRetryAfter());
                    $this->logger->warning(
                        "Telegram rate limit hit (retry_after={$retryAfter}s): {$e->getMessage()}"
                    );
                    delay($retryAfter * 1000);
                    continue;
                } catch (TransientException $e) {
                    $failureCount++;
                    $connectionFailureCount++;

                    $delaySeconds = $this->computeBackoff($failureCount, $backoffBase, $maxBackoff);

                    $this->logger->warning(
                        "Transient poller error (attempt {$failureCount}): {$e->getMessage()} | retry in {$delaySeconds}s"
                    );

                    if ($connectionFailureCount >= self::RECONNECT_AFTER_CONSECUTIVE_FAILURES) {
                        $this->logger->info(
                            "Resetting HTTP client after {$connectionFailureCount} consecutive transient failures"
                        );
                        try {
                            $this->client->reconnect();
                        } catch (\Throwable $re) {
                            $this->logger->error("HTTP client reconnect failed: ".$re->getMessage());
                        }
                        $connectionFailureCount = 0;
                    }

                    delay((int) round($delaySeconds * 1000));
                    continue;
                } catch (PermanentException $e) {
                    $this->logger->error(
                        "Permanent Telegram error, stopping poller: {$e->getMessage()}"
                    );
                    $this->running = false;
                    throw $e;
                } catch (\Throwable $e) {
                    // Unknown error: err on the side of resiliency, but keep
                    // the bot observable via logs.
                    $failureCount++;
                    $delaySeconds = $this->computeBackoff($failureCount, $backoffBase, $maxBackoff);

                    $this->logger->error(
                        "Unexpected poller error (attempt {$failureCount}): {$e->getMessage()} | retry in {$delaySeconds}s"
                    );

                    delay((int) round($delaySeconds * 1000));
                    continue;
                }
            }

            $this->logger->info("Poller stopped");
        });

        $this->mainFuture->await();
    }

    // Stop the poller loop
    public function stop(): void { $this->running = false; }

    // Check if poller is currently running
    public function isRunning(): bool { return $this->running; }
}
