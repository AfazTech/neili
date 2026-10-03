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
use Neili\Media;

trait HandlesWebhooks
{
    /**
     * Set webhook for receiving updates.
     *
     * The certificate parameter accepts either a Media object or a local
     * file path string. When provided, the request is sent as
     * multipart/form-data with the certificate uploaded as an InputFile,
     * as required by the Telegram Bot API.
     *
     * @param string           $url                HTTPS URL to send updates to
     * @param Media|string|null $certificate       Public key certificate (local file)
     * @param int|null         $maxConnections     Maximum allowed simultaneous connections
     * @param array|null       $allowedUpdates     List of update types to receive
     * @param bool|null        $dropPendingUpdates Drop pending updates on success
     * @param string|null      $secretToken        Secret token for webhook verification
     * @param string|null      $ipAddress          Fixed IP address for webhook requests
     */
    public function setWebhook(
        string $url,
        Media|string|null $certificate = null,
        ?int $maxConnections = null,
        ?array $allowedUpdates = null,
        ?bool $dropPendingUpdates = null,
        ?string $secretToken = null,
        ?string $ipAddress = null
    ): Future {
        $payload = ['url' => $url];

        if ($maxConnections !== null) {
            $payload['max_connections'] = $maxConnections;
        }
        if ($allowedUpdates !== null) {
            $payload['allowed_updates'] = json_encode($allowedUpdates);
        }
        if ($dropPendingUpdates !== null) {
            $payload['drop_pending_updates'] = $dropPendingUpdates;
        }
        if ($secretToken !== null) {
            $payload['secret_token'] = $secretToken;
        }
        if ($ipAddress !== null) {
            $payload['ip_address'] = $ipAddress;
        }

        if ($certificate !== null) {
            $path = $certificate instanceof Media ? $certificate->filePath : $certificate;
            return $this->requestWithFile('setWebhook', $payload, ['certificate' => $path]);
        }

        return $this->request('setWebhook', $payload);
    }

    /**
     * Delete webhook
     */
    public function deleteWebhook(?bool $dropPendingUpdates = null): Future
    {
        $payload = [];
        if ($dropPendingUpdates !== null)
            $payload['drop_pending_updates'] = $dropPendingUpdates;
        return $this->request('deleteWebhook', $payload);
    }

    /**
     * Get webhook info
     */
    public function getWebhookInfo(): Future
    {
        return $this->request('getWebhookInfo');
    }
}
