<?php

declare(strict_types=1);

namespace Neili\Client\Concerns;

use Amp\Future;

trait HandlesUpdates
{
    /**
     * Handle incoming update.
     *
     * Supports three delivery modes:
     *   - CLI mode: used by the multi-process worker (payload in $argv[1]).
     *   - Multi-process webhook mode: forks a PHP CLI worker per update.
     *   - Fast-ack webhook mode: replies 200 OK to Telegram immediately and
     *     continues processing the update in the current PHP request.
     *
     * Multi-process and fast-ack modes are mutually exclusive; Settings
     * enforces that invariant at configuration time.
     */
    public function handleUpdate(?string $secretToken = null): array
    {
        $isCli = (php_sapi_name() === 'cli');
        global $argv;

        if (!$isCli) {
            // Webhook mode
            if ($secretToken !== null) {
                $headerToken = $this->readSecretTokenHeader();
                if ($headerToken !== $secretToken) {
                    throw new \RuntimeException('Invalid secret token');
                }
            }

            $rawInput = file_get_contents('php://input');
            $update = json_decode($rawInput, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON: ' . json_last_error_msg());
            }

            // Multi-process support: fork a new PHP process for the update.
            // Both the PHP binary and the script path are escaped because
            // either can contain shell metacharacters (paths with spaces,
            // for instance), and shell injection would be catastrophic.
            if ($this->settings->isMultiProcess()) {
                $payload = base64_encode(json_encode($update));
                $executedFile = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
                $phpBinary = $this->settings->getPhpBinary();

                $command = sprintf(
                    '%s %s %s > /dev/null 2>&1 &',
                    escapeshellarg($phpBinary),
                    escapeshellarg($executedFile),
                    escapeshellarg($payload),
                );
                exec($command);
                http_response_code(200);
                exit;
            }

            // Fast-ack support: acknowledge Telegram right away, then let
            // the caller process the update in this request lifecycle.
            if ($this->settings->isFastAck()) {
                $this->acknowledgeWebhook();
            }

            return $update;
        }

        // CLI mode
        if (!isset($argv[1])) {
            throw new \RuntimeException('No payload provided in CLI');
        }
        $update = json_decode(base64_decode($argv[1]), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON in CLI payload: ' . json_last_error_msg());
        }
        return $update;
    }

    /**
     * Read the X-Telegram-Bot-Api-Secret-Token header.
     *
     * Prefers the normalised $_SERVER entry (always present in web SAPIs),
     * then falls back to getallheaders() with a case-insensitive lookup,
     * because different servers normalise header casing differently.
     */
    private function readSecretTokenHeader(): ?string
    {
        $fromServer = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;
        if (is_string($fromServer) && $fromServer !== '') {
            return $fromServer;
        }

        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                foreach ($headers as $name => $value) {
                    if (strcasecmp((string) $name, 'X-Telegram-Bot-Api-Secret-Token') === 0) {
                        return is_string($value) ? $value : null;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Send an immediate 200 OK to the webhook caller and detach the response,
     * so the current PHP request keeps running in the background without
     * blocking Telegram.
     *
     * Prefers fastcgi_finish_request(); falls back to
     * litespeed_finish_request(), frankenphp_finish_request(), and finally
     * a best-effort output buffer flush + flush().
     */
    private function acknowledgeWebhook(): void
    {
        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }
        if (function_exists('ini_set')) {
            @ini_set('max_execution_time', '0');
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if (!headers_sent()) {
            http_response_code(200);
        }
        echo 'OK';

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        } elseif (function_exists('frankenphp_finish_request')) {
            frankenphp_finish_request();
        } else {
            while (ob_get_level() > 0) {
                if (!ob_end_flush()) {
                    break;
                }
            }
            flush();
        }
    }

    /**
     * Get updates.
     *
     * When $allowedUpdates is provided, the list is JSON-encoded as required
     * by the Bot API. An empty array is meaningful: it tells Telegram to
     * exclude chat_member, message_reaction, and message_reaction_count.
     */
    public function getUpdates(
        ?int $offset = null,
        ?int $limit = null,
        ?int $timeout = null,
        ?array $allowedUpdates = null,
        ?array $extraParams = null
    ): Future {
        $payload = [];

        if ($offset !== null) {
            $payload['offset'] = $offset;
        }

        if ($limit !== null) {
            $payload['limit'] = $limit;
        }

        if ($timeout !== null) {
            $payload['timeout'] = $timeout;
        }

        if ($allowedUpdates !== null) {
            $payload['allowed_updates'] = json_encode($allowedUpdates);
        }

        return $this->request('getUpdates', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }
}
