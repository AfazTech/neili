<?php

/**
 * @author Abolfazl Majidi (Afaz)
 * @package neili
 * @license https://opensource.org/licenses/MIT
 * @link https://github.com/AfazTech/neili
 */

declare(strict_types=1);

namespace Neili;

use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
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

    /**
     * Telegram bot settings (token, API URL, etc)
     */
    private Settings $settings;

    /**
     * HTTP client for async requests
     */
    private HttpClient $httpClient;

    /**
     * Extra grace period (seconds) added on top of a getUpdates long-poll
     * timeout so that the transport does not abort a still-valid long poll.
     */
    private const LONG_POLL_GRACE_SECONDS = 10;

    /**
     * Constructor
     * Initializes the HTTP client and stores settings.
     *
     * NOTE: In amphp/http-client v5 the transfer / connect timeouts are NOT
     * configurable on HttpClientBuilder; they must be set per-Request.
     * We therefore keep the builder at its defaults and apply Settings values
     * on every Request created by request() / requestWithFile().
     */
    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
        $this->httpClient = HttpClientBuilder::buildDefault();
    }

    /**
     * Get current settings
     */
    public function getSettings(): Settings
    {
        return $this->settings;
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
        $this->httpClient = HttpClientBuilder::buildDefault();
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
