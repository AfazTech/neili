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
 * Represents a non-recoverable Telegram API error (e.g. 401 invalid token,
 * 400 malformed request). Retrying will never succeed, so the Poller should
 * stop and surface the error.
 */
class PermanentException extends NeiliException
{
    /**
     * Telegram error_code (HTTP-like) or 0 when unavailable.
     */
    private int $errorCode;

    /**
     * Raw parameters block from the Telegram response.
     */
    private array $parameters;

    public function __construct(
        string $message,
        int $errorCode = 0,
        array $parameters = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode, $previous);
        $this->errorCode = $errorCode;
        $this->parameters = $parameters;
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }
}
