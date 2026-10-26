<?php

/**
 * @author Abolfazl Majidi (Afaz)
 * @package neili
 * @license https://opensource.org/licenses/MIT
 * @link https://github.com/AfazTech/neili
 */
declare(strict_types=1);

namespace Neili;

class Settings
{
    /**
     * Telegram bot access token
     */
    private string $accessToken;

    /**
     * Base API URL
     */
    private string $apiUrl = 'https://api.telegram.org/bot';

    /**
     * Enable SSL verification for API requests
     */
    private bool $apiVerifySSL = true;

    /**
     * Request timeout in seconds
     */
    private int $timeout = 10;

    /**
     * Connection timeout in seconds
     */
    private int $connectionTimeout = 5;

    /**
     * Flag to enable multi-process usage
     */
    private bool $useMultiProcess = false;

    /**
     * Flag to enable fast-ack webhook mode.
     *
     * In fast-ack mode the webhook endpoint replies 200 OK to Telegram
     * immediately, detaches the response, and continues processing the
     * update in the same PHP request lifecycle. Mutually exclusive with
     * multi-process mode.
     */
    private bool $fastAck = false;

    /**
     * Path to PHP binary for multi-process execution
     */
    private string $phpBinary = '/usr/bin/php';

    /**
     * Timeout for poller getUpdates requests
     */
    private int $pollerTimeout = 3;

    /**
     * Base seconds for exponential backoff in poller
     */
    private int $pollerBackoffBase = 1;

    /**
     * Maximum backoff seconds in poller
     */
    private int $pollerMaxBackoff = 32;

    /**
     * Maximum concurrent async handlers in poller
     */
    private ?int $pollerMaxConcurrency = null;

    /**
     * Update types requested from Telegram by the poller.
     *
     * Defaults to the full list of update types Neili supports so that
     * opt-in types such as chat_member, message_reaction, and
     * message_reaction_count are delivered out of the box. Telegram's
     * own default allowed_updates explicitly excludes those three, so
     * relying on the server default silently drops them.
     *
     * Set an empty array to restore Telegram's default filtering, or
     * pass an explicit list to restrict the update stream further.
     *
     * @var array<int, string>
     */
    private array $pollerAllowedUpdates = [
        'message',
        'edited_message',
        'channel_post',
        'edited_channel_post',
        'business_connection',
        'business_message',
        'edited_business_message',
        'deleted_business_messages',
        'message_reaction',
        'message_reaction_count',
        'inline_query',
        'chosen_inline_result',
        'callback_query',
        'shipping_query',
        'pre_checkout_query',
        'purchased_paid_media',
        'poll',
        'poll_answer',
        'my_chat_member',
        'chat_member',
        'chat_join_request',
        'chat_boost',
        'removed_chat_boost',
        'managed_bot',
        'guest_message',
    ];

    /**
     * Optional custom HTTP client.
     *
     * Typed as object rather than Amp\Http\Client\HttpClient because that
     * symbol has been observed as a class in some amphp/http-client builds.
     * Any injected client must expose a compatible request() method.
     */
    private ?object $httpClient = null;

    /**
     * Set bot access token
     */
    public function setAccessToken(string $token): self { $this->accessToken = $token; return $this; }

    /**
     * Get bot access token
     */
    public function getAccessToken(): string { return $this->accessToken; }

    /**
     * Set API URL
     */
    public function setApiUrl(string $url): self { $this->apiUrl = $url; return $this; }

    /**
     * Get API URL
     */
    public function getApiUrl(): string { return $this->apiUrl; }

    /**
     * Enable or disable SSL verification
     */
    public function setApiVerifySSL(bool $state): self { $this->apiVerifySSL = $state; return $this; }

    /**
     * Check if SSL verification is enabled
     */
    public function isApiVerifySSL(): bool { return $this->apiVerifySSL; }

    /**
     * Set request timeout and optionally connection timeout
     */
    public function setTimeout(int $timeout, ?int $connectionTimeout = null): self {
        $this->timeout = $timeout;
        if ($connectionTimeout !== null) $this->connectionTimeout = $connectionTimeout;
        return $this;
    }

    /**
     * Get request timeout
     */
    public function getTimeout(): int { return $this->timeout; }

    /**
     * Get connection timeout
     */
    public function getConnectionTimeout(): int { return $this->connectionTimeout; }

    /**
     * Enable or disable multi-process.
     *
     * Mutually exclusive with fast-ack mode.
     *
     * @throws \RuntimeException if exec is disabled or fast-ack is enabled
     */
    public function setMultiProcess(bool $state): self {
        if ($state && $this->fastAck) {
            throw new \RuntimeException(
                'Cannot enable multi-process while fast-ack is enabled. Disable fast-ack first.'
            );
        }
        if ($state && !function_exists('exec')) throw new \RuntimeException("Cannot enable multi-process: exec disabled");
        $this->useMultiProcess = $state;
        return $this;
    }

    /**
     * Check if multi-process is enabled
     */
    public function isMultiProcess(): bool { return $this->useMultiProcess; }

    /**
     * Enable or disable fast-ack webhook mode.
     *
     * In fast-ack mode the webhook endpoint replies 200 OK to Telegram
     * immediately, detaches the response, and continues processing the
     * update in the same PHP request lifecycle. Mutually exclusive with
     * multi-process mode.
     *
     * @throws \RuntimeException if multi-process mode is already enabled
     */
    public function setFastAck(bool $state): self {
        if ($state && $this->useMultiProcess) {
            throw new \RuntimeException(
                'Cannot enable fast-ack while multi-process is enabled. Disable multi-process first.'
            );
        }
        $this->fastAck = $state;
        return $this;
    }

    /**
     * Check if fast-ack webhook mode is enabled
     */
    public function isFastAck(): bool { return $this->fastAck; }

    /**
     * Set PHP binary path for multi-process execution
     */
    public function setPhpBinary(string $path): self { $this->phpBinary = $path; return $this; }

    /**
     * Get PHP binary path
     */
    public function getPhpBinary(): string { return $this->phpBinary; }

    /**
     * Set base seconds for poller backoff
     */
    public function setPollerBackoffBase(int $seconds): self { $this->pollerBackoffBase = $seconds; return $this; }

    /**
     * Get poller backoff base seconds
     */
    public function getPollerBackoffBase(): int { return $this->pollerBackoffBase; }

    /**
     * Set maximum backoff seconds for poller
     */
    public function setPollerMaxBackoff(int $seconds): self { $this->pollerMaxBackoff = $seconds; return $this; }

    /**
     * Get poller maximum backoff seconds
     */
    public function getPollerMaxBackoff(): int { return $this->pollerMaxBackoff; }

    /**
     * Set maximum concurrent handlers for poller
     */
    public function setPollerMaxConcurrency(?int $n): self { $this->pollerMaxConcurrency = $n; return $this; }

    /**
     * Get maximum concurrent handlers
     */
    public function getPollerMaxConcurrency(): ?int { return $this->pollerMaxConcurrency; }

    /**
     * Set the update types the poller requests from Telegram.
     *
     * @param array<int, string> $types
     */
    public function setPollerAllowedUpdates(array $types): self {
        $this->pollerAllowedUpdates = array_values($types);
        return $this;
    }

    /**
     * Get the update types the poller requests from Telegram.
     *
     * @return array<int, string>
     */
    public function getPollerAllowedUpdates(): array {
        return $this->pollerAllowedUpdates;
    }

    /**
     * Inject a pre-configured HTTP client.
     */
    public function setHttpClient(object $client): self { $this->httpClient = $client; return $this; }

    /**
     * Get the injected HTTP client, if any.
     */
    public function getHttpClient(): ?object { return $this->httpClient; }

    /**
     * Set poller request timeout
     */
    public function setPollerTimeout(int $seconds): self { $this->pollerTimeout = $seconds; return $this; }

    /**
     * Get poller request timeout
     */
    public function getPollerTimeout(): int { return $this->pollerTimeout; }
}
