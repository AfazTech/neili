<?php

/**
 * @author Abolfazl Majidi (Afaz)
 * @package neili
 * @license https://opensource.org/licenses/MIT
 * @link https://github.com/AfazTech/neili
 */

declare(strict_types=1);

namespace Neili\Client\Concerns;

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
     * raw body are included in the exception message so that it is
     * possible to distinguish a real Telegram 400 (JSON with
     * "description") from a proxy / upstream failure (HTML).
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
     * The multipart body is constructed as a single pre-sized string
     * instead of using Amp\Http\Client\Form. With Form, the underlying
     * HTTP client sends the body with Transfer-Encoding: chunked
     * because the form's length cannot be known ahead of time (file
     * handles are streams). Telegram's own nginx rejects chunked
     * multipart uploads with 400 Bad Request, so a real-world
     * sendDocument of a ~160KB HTML file fails even though the exact
     * same request sent with curl (which always sends a Content-Length)
     * succeeds.
     *
     * Building the body as a plain string lets Amp\Http\Client set a
     * correct Content-Length automatically (the string is buffered),
     * which is what Telegram expects. Every uploaded file uses the
     * neutral "application/octet-stream" content type; Telegram
     * identifies documents by file name and content, not by MIME, so
     * the user still receives the same file with the same name.
     */
    private function requestWithFile(string $method, array $fields, array $files = []): Future
    {
        $url = $this->settings->getApiUrl() . $this->settings->getAccessToken() . '/' . $method;

        return async(function () use ($url, $method, $fields, $files) {
            try {
                $boundary = '----NeiliBoundary' . bin2hex(random_bytes(16));

                $body = '';

                foreach ($fields as $key => $value) {
                    $safeKey = str_replace(['"', "\r", "\n"], ['', '', ''], (string) $key);
                    $body .= "--{$boundary}\r\n";
                    $body .= "Content-Disposition: form-data; name=\"{$safeKey}\"\r\n\r\n";
                    $body .= (string) $value . "\r\n";
                }

                foreach ($files as $key => $filePath) {
                    $realPath = realpath($filePath);
                    if (!$realPath) {
                        throw new \RuntimeException("File not found: $filePath");
                    }

                    $fileContent = @file_get_contents($realPath);
                    if ($fileContent === false) {
                        throw new \RuntimeException("Cannot read file: $realPath");
                    }

                    $filename = basename($realPath);
                    $safeKey = str_replace(['"', "\r", "\n"], ['', '', ''], (string) $key);
                    $safeFilename = str_replace(['"', "\r", "\n"], ['', '', ''], $filename);

                    $body .= "--{$boundary}\r\n";
                    $body .= "Content-Disposition: form-data; name=\"{$safeKey}\"; filename=\"{$safeFilename}\"\r\n";
                    $body .= "Content-Type: application/octet-stream\r\n\r\n";
                    $body .= $fileContent . "\r\n";
                }

                $body .= "--{$boundary}--\r\n";

                $request = new Request($url, 'POST');
                $request->setHeader('Content-Type', "multipart/form-data; boundary={$boundary}");
                $request->setBody($body);
                $this->applyTimeouts($request, $method, $fields);

                $response = $this->httpClient->request($request);
                $status = $response->getStatus();
                $responseBody = $response->getBody()->buffer();

                return $this->handleResponse($status, (string) $responseBody);
            } catch (\Throwable $e) {
                throw $this->translateTransportError($e);
            }
        });
    }
}
