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

use Amp\Future;

trait HandlesUpdates
{
    /**
     * Handle incoming update
     * Supports both CLI (for multi-process) and webhook mode
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

            return $update;

        } else {
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
    }

    /**
     * Get updates.
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
