<?php

/**
 * @author Abolfazl Majidi (Afaz)
 * @package neili
 * @license https://opensource.org/licenses/MIT
 * @link https://github.com/AfazTech/neili
 */

declare(strict_types=1);

namespace Neili\Exceptions;

/**
 * Represents a recoverable failure such as a network error, a timeout,
 * or a 5xx response. The Poller should log and retry with backoff
 * instead of terminating.
 */
class TransientException extends NeiliException
{
}
