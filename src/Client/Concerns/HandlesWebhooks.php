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

trait HandlesWebhooks
{
    /**
     * Set webhook for receiving updates
     * @param string $url Webhook URL
     * @param string|null $certificate Path to public key certificate
     * @param int|null $maxConnections Maximum allowed connections
     * @param array|null $allowedUpdates List of update types to receive
     * @param bool|null $dropPendingUpdates Drop pending updates
     * @param string|null $secretToken Secret token for webhook verification
     */
    public function setWebhook(string $url, ?string $certificate = null, ?int $maxConnections = null, ?array $allowedUpdates = null, ?bool $dropPendingUpdates = null, ?string $secretToken = null): Future
    {
        $payload = ['url' => $url];
        if ($certificate !== null)
            $payload['certificate'] = $certificate;
        if ($maxConnections !== null)
            $payload['max_connections'] = $maxConnections;
        if ($allowedUpdates !== null)
            $payload['allowed_updates'] = $allowedUpdates;
        if ($dropPendingUpdates !== null)
            $payload['drop_pending_updates'] = $dropPendingUpdates;
        if ($secretToken !== null)
            $payload['secret_token'] = $secretToken;
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
