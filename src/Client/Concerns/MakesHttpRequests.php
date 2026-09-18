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
     * Resolve the appropriate per-request transfer timeout for the given method.
     * getUpdates is a long-poll and needs a timeout >= the Telegram "timeout"
     * payload value, otherwise the transport aborts it before Telegram replies.
     */
    private function resolveTransferTimeout(string $method, array $params): float
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
        $request->setTransferTimeout($this->resolveTransferTimeout($method, $params));
        $request->setTcpConnectTimeout((float) $this->settings->getConnectionTimeout());
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
                $body = $response->getBody()->buffer();
                return json_decode($body, true);
            } catch (\Throwable $e) {
                $this->settings->getLogger()->error(
                    "HTTP request failed | " .
                    "Message: " . $e->getMessage() .
                    " | File: " . $e->getFile() .
                    " | Line: " . $e->getLine() .
                    " | Trace: " . $e->getTraceAsString()
                );
                throw $e;
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
                $body = $response->getBody()->buffer();
                return json_decode($body, true);
            } catch (\Throwable $e) {
                $this->settings->getLogger()->error(
                    "HTTP request failed | " .
                    "Message: " . $e->getMessage() .
                    " | File: " . $e->getFile() .
                    " | Line: " . $e->getLine() .
                    " | Trace: " . $e->getTraceAsString()
                );
                throw $e;
            }
        });
    }
}
