<?php

/**
 * @version 2.2.11
 * @author Abolfazl Majidi (Afaz)
 * @package neili
 * @license https://opensource.org/licenses/MIT
 * @link https://github.com/AfazTech/neili
 */

declare(strict_types=1);

namespace Neili\Client\Concerns;

use Amp\Http\Client\Form;
use Amp\Http\Client\Request;
use Amp\Future;
use Neili\Exceptions\NeiliException;
use Neili\Exceptions\PermanentException;
use Neili\Exceptions\RateLimitException;
use Neili\Exceptions\TransientException;
use function Amp\async;

trait MakesHttpRequests
{
    /**
     * Checks if a string is a valid URL
     */
    private static function isUrl(string $string): bool
    {
        return filter_var($string, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Resolve the appropriate per-request timeout for the given method.
     *
     * getUpdates is a long-poll: the transport must keep the connection alive
     * for at least the Telegram "timeout" value plus a small grace period,
     * otherwise the request is aborted before Telegram replies.
     */
    private function resolveRequestTimeout(string $method, array $params): float
    {
        if ($method === 'getUpdates') {
            $telegramTimeout = (int) ($params['timeout'] ?? $this->settings->getPollerTimeout());
            return (float) ($telegramTimeout + self::LONG_POLL_GRACE_SECONDS);
        }

        return (float) $this->settings->getTimeout();
    }

    /**
     * Apply Settings-based timeouts to a Request.
     *
     * For long-polling (getUpdates), both the transfer timeout and the
     * inactivity timeout must be extended to "poll timeout + grace".
     * The inactivity timeout is critical: during a long poll no bytes are
     * exchanged while Telegram waits for an update, so a short inactivity
     * timeout would tear the connection down even though the request is
     * still valid.
     */
    private function applyTimeouts(Request $request, string $method, array $params): void
    {
        $timeout = $this->resolveRequestTimeout($method, $params);

        $request->setTransferTimeout($timeout);
        $request->setInactivityTimeout($timeout);
        $request->setTcpConnectTimeout((float) $this->settings->getConnectionTimeout());
    }

    /**
     * Translate HTTP status + decoded Telegram payload into Neili exceptions.
     *
     * Classification rules:
     *   - HTTP 5xx                    -> TransientException
     *   - Telegram error_code 429     -> RateLimitException (with retry_after)
     *   - Telegram error_code >= 500  -> TransientException
     *   - any other non-ok response   -> PermanentException
     *
     * @throws TransientException on transient failures
     * @throws RateLimitException on HTTP 429
     * @throws PermanentException on non-recoverable errors
     */
    private function handleResponse(int $status, string $body): array
    {
        if ($status >= 500 && $status < 600) {
            throw new TransientException("Telegram API returned HTTP {$status}", $status);
        }

        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            throw new TransientException(
                "Invalid JSON response from Telegram API (HTTP {$status})",
                $status
            );
        }

        if (array_key_exists('ok', $decoded) && $decoded['ok'] === false) {
            $code = (int) ($decoded['error_code'] ?? $status);
            $description = (string) ($decoded['description'] ?? 'Unknown Telegram API error');
            $parameters = (array) ($decoded['parameters'] ?? []);

            if ($code === 429) {
                $retryAfter = (int) ($parameters['retry_after'] ?? 1);
                throw new RateLimitException($description, $retryAfter, $parameters);
            }

            if ($code >= 500) {
                throw new TransientException($description, $code);
            }

            throw new PermanentException($description, $code, $parameters);
        }

        return $decoded;
    }

    /**
     * Convert a low-level transport failure into a TransientException so the
     * Poller can retry without dying. PHP engine errors (Error subclasses)
     * are re-thrown untouched, since they indicate bugs, not transient faults.
     */
    private function translateTransportError(\Throwable $e): \Throwable
    {
        if ($e instanceof NeiliException) {
            return $e;
        }

        if ($e instanceof \Error) {
            return $e;
        }

        return new TransientException("Transport error: {$e->getMessage()}", (int) $e->getCode(), $e);
    }

    /**
     * Perform async HTTP request to Telegram API
     */
    private function request(string $method, array $params = []): Future
    {
        $url = $this->settings->getApiUrl() . $this->settings->getAccessToken() . '/' . $method;

        return async(function () use ($url, $method, $params) {
            try {
                $request = new Request($url, 'POST');
                $request->setHeader('Content-Type', 'application/json');
                $request->setBody(json_encode($params));
                $this->applyTimeouts($request, $method, $params);

                $response = $this->httpClient->request($request);
                $status = $response->getStatus();
                $body = $response->getBody()->buffer();

                return $this->handleResponse($status, (string) $body);
            } catch (\Throwable $e) {
                $this->settings->getLogger()->error(
                    "HTTP request failed | " .
                    "Message: " . $e->getMessage() .
                    " | File: " . $e->getFile() .
                    " | Line: " . $e->getLine() .
                    " | Trace: " . $e->getTraceAsString()
                );
                throw $this->translateTransportError($e);
            }
        });
    }

    /**
     * Send request with file upload support
     * Useful for photos, documents, audio, stickers, etc.
     */
    private function requestWithFile(string $method, array $fields, array $files = []): Future
    {
        $url = $this->settings->getApiUrl() . $this->settings->getAccessToken() . '/' . $method;

        return async(function () use ($url, $method, $fields, $files) {
            try {
                $form = new Form();

                foreach ($fields as $key => $value) {
                    $form->addField($key, (string) $value);
                }

                foreach ($files as $key => $filePath) {
                    $realPath = realpath($filePath);
                    if (!$realPath)
                        throw new \RuntimeException("File not found: $filePath");
                    $form->addFile($key, $realPath);
                }

                $request = new Request($url, 'POST');
                $request->setBody($form);
                $this->applyTimeouts($request, $method, $fields);

                $response = $this->httpClient->request($request);
                $status = $response->getStatus();
                $body = $response->getBody()->buffer();

                return $this->handleResponse($status, (string) $body);
            } catch (\Throwable $e) {
                $this->settings->getLogger()->error(
                    "HTTP request failed | " .
                    "Message: " . $e->getMessage() .
                    " | File: " . $e->getFile() .
                    " | Line: " . $e->getLine() .
                    " | Trace: " . $e->getTraceAsString()
                );
                throw $this->translateTransportError($e);
            }
        });
    }
}
