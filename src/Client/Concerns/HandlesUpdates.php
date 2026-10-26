<?php

/**
 * @author Abolfazl Majidi (Afaz)
 * @package neili
 * @license https://opensource.org/licenses/MIT
 * @link https://github.com/AfazTech/neili
 */

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
            $headers = getallheaders();
            if ($secretToken !== null) {
                $headerToken = $headers['X-Telegram-Bot-Api-Secret-Token'] ?? null;
                if ($headerToken !== $secretToken) {
                    throw new \RuntimeException('Invalid secret token');
                }
            }

            $rawInput = file_get_contents('php://input');
            $update = json_decode($rawInput, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON: ' . json_last_error_msg());
            }

            // Multi-process support: fork a new PHP process for the update
            if ($this->settings->isMultiProcess()) {
                $payload = base64_encode(json_encode($update));
                $executedFile = $_SERVER['SCRIPT_FILENAME'];
                $phpBinary = $this->settings->getPhpBinary();
                exec("{$phpBinary} {$executedFile} '$payload' > /dev/null 2>&1 &");
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
