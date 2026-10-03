<?php

/**
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
use Neili\Media;
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
     */
    private function applyTimeouts(Request $request, string $method, array $params): void
    {
        $timeout = $this->resolveRequestTimeout($method, $params);

        $request->setTransferTimeout($timeout);
        $request->setInactivityTimeout($timeout);
        $request->setTcpConnectTimeout((float) $this->settings->getConnectionTimeout());
    }

    /**
     * Build an InputProfilePhoto payload and companion files array.
     *
     * @param array|Media $photo
     * @return array Tuple of [payload, files]
     */
    private function buildInputProfilePhoto(array|Media $photo): array
    {
        if (!($photo instanceof Media)) {
            return [$photo, []];
        }

        $extension = strtolower(pathinfo($photo->filePath, PATHINFO_EXTENSION));
        $isAnimated = in_array($extension, ['mp4', 'mov', 'mpeg', 'mpg'], true);
        $attachKey = 'profile_photo_file';
        $files = [$attachKey => $photo->filePath];

        if ($isAnimated) {
            return [
                [
                    'type' => 'animated',
                    'animation' => 'attach://' . $attachKey,
                ],
                $files,
            ];
        }

        return [
            [
                'type' => 'static',
                'photo' => 'attach://' . $attachKey,
            ],
            $files,
        ];
    }

    /**
     * Replace Media objects inside an InputMedia payload with
     * "attach://<key>" references, collecting the corresponding local files
     * into the provided $attachments map (by reference).
     *
     * The set of fields scanned covers every InputMedia variant defined by
     * the Telegram Bot API which can carry a binary payload: media, photo,
     * thumbnail, and cover.
     *
     * @param array $media InputMedia payload
     * @param array $attachments File map populated by reference
     * @return array Normalized InputMedia payload
     */
    private function extractMediaAttachments(array $media, array &$attachments): array
    {
        foreach (['media', 'photo', 'thumbnail', 'cover'] as $field) {
            if (!isset($media[$field]) || !($media[$field] instanceof Media)) {
                continue;
            }

            $attachKey = 'file_' . $field;
            $suffix = 0;
            while (isset($attachments[$attachKey])) {
                $suffix++;
                $attachKey = 'file_' . $field . '_' . $suffix;
            }

            $attachments[$attachKey] = $media[$field]->filePath;
            $media[$field] = 'attach://' . $attachKey;
        }

        return $media;
    }

    /**
     * Normalize an InputPollMedia / InputPollOptionMedia value.
     *
     * Both shapes share the same grammar: an object with a "type" field and,
     * for concrete media variants, a "media" (or "photo") field that can hold
     * a Media object. Location and venue variants carry coordinates instead.
     *
     * @param array|null $media
     * @param array      $attachments File map populated by reference
     * @param string     $prefix      Unique prefix for generated attach keys
     * @return array|null
     */
    private function extractPollMediaAttachments(?array $media, array &$attachments, string $prefix): ?array
    {
        if ($media === null) {
            return null;
        }

        $scoped = [];
        $normalized = $this->extractMediaAttachments($media, $scoped);

        foreach ($scoped as $key => $path) {
            $attachments[$prefix . '_' . $key] = $path;
        }

        foreach (['media', 'photo'] as $field) {
            if (
                isset($normalized[$field])
                && is_string($normalized[$field])
                && str_starts_with($normalized[$field], 'attach://')
            ) {
                $originalKey = substr($normalized[$field], 8);
                $normalized[$field] = 'attach://' . $prefix . '_' . $originalKey;
            }
        }

        return $normalized;
    }

    /**
     * Translate HTTP status + decoded Telegram payload into Neili exceptions.
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
     * Poller can retry without dying.
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
     * Send request with file upload support.
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
