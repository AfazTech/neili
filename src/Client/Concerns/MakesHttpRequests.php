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
    private static function isUrl(string $string): bool
    {
        return filter_var($string, FILTER_VALIDATE_URL) !== false;
    }

    private function resolveRequestTimeout(string $method, array $params): float
    {
        if ($method === 'getUpdates') {
            $telegramTimeout = (int) ($params['timeout'] ?? $this->settings->getPollerTimeout());
            return (float) ($telegramTimeout + self::LONG_POLL_GRACE_SECONDS);
        }

        return (float) $this->settings->getTimeout();
    }

    private function applyTimeouts(Request $request, string $method, array $params): void
    {
        $timeout = $this->resolveRequestTimeout($method, $params);

        $request->setTransferTimeout($timeout);
        $request->setInactivityTimeout($timeout);
        $request->setTcpConnectTimeout((float) $this->settings->getConnectionTimeout());
    }

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
     * When the body is not valid JSON, the first 500 characters of the
     * raw body are included in the exception message. This makes it
     * possible to tell apart a real Telegram 400 (JSON with
     * "description") from a proxy / WAF response (HTML).
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
            $preview = trim(preg_replace('/\s+/', ' ', mb_substr($body, 0, 500)) ?? '');
            throw new TransientException(
                "Invalid JSON response from Telegram API (HTTP {$status}). "
                . "Body preview: {$preview}",
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
                throw $this->translateTransportError($e);
            }
        });
    }

    /**
     * Send a request with file upload support.
     *
     * Every uploaded file is sent with the generic
     * "application/octet-stream" MIME type instead of the MIME guessed
     * from its extension. Telegram identifies documents by file name
     * and content, not by the multipart Content-Type, but some proxies
     * and WAFs reject uploads whose MIME looks like "text/html". Using
     * a neutral binary MIME avoids that class of 400 responses without
     * changing what the user receives.
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
                    if (!$realPath) {
                        throw new \RuntimeException("File not found: $filePath");
                    }
                    $form->addFile($key, $realPath, 'application/octet-stream');
                }

                $request = new Request($url, 'POST');
                $request->setBody($form);
                $this->applyTimeouts($request, $method, $fields);

                $response = $this->httpClient->request($request);
                $status = $response->getStatus();
                $body = $response->getBody()->buffer();

                return $this->handleResponse($status, (string) $body);
            } catch (\Throwable $e) {
                throw $this->translateTransportError($e);
            }
        });
    }
}
