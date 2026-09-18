<?php

/**
 * @version 2.2.11
 * @author Abolfazl Majidi (Afaz)
 * @package neili
 * @license https://opensource.org/licenses/MIT
 * @link https://github.com/AfazTech/neili
 */

declare(strict_types=1);

namespace Neili\Exceptions;

/**
 * Represents HTTP 429 returned by Telegram, carrying the retry_after hint
 * so callers can wait exactly as long as requested.
 */
class RateLimitException extends NeiliException
{
    /**
     * Seconds Telegram asked the bot to wait before retrying.
     */
    private int $retryAfter;

    /**
     * Raw parameters block from the Telegram response.
     */
    private array $parameters;

    public function __construct(
        string $message,
        int $retryAfter = 1,
        array $parameters = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 429, $previous);
        $this->retryAfter = max(1, $retryAfter);
        $this->parameters = $parameters;
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }
}
