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

trait HandlesPayments
{
    /**
     * Send invoice for payments
     */
    public function sendInvoice(int $chatId, string $title, string $description, string $payloadStr, string $providerToken, string $currency, array $prices, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'title' => $title, 'description' => $description, 'payload' => $payloadStr, 'provider_token' => $providerToken, 'currency' => $currency, 'prices' => json_encode($prices)];
        return $this->request('sendInvoice', $payload + ($extraParams ?? []));
    }

    /**
     * Answer shipping query
     */
    public function answerShippingQuery(string $shippingQueryId, bool $ok, ?array $shippingOptions = null, ?string $errorMessage = null): Future
    {
        $payload = ['shipping_query_id' => $shippingQueryId, 'ok' => $ok];
        if ($shippingOptions !== null)
            $payload['shipping_options'] = json_encode($shippingOptions);
        if ($errorMessage !== null)
            $payload['error_message'] = $errorMessage;
        return $this->request('answerShippingQuery', $payload);
    }

    /**
     * Answer pre-checkout query
     */
    public function answerPreCheckoutQuery(string $preCheckoutQueryId, bool $ok, ?string $errorMessage = null): Future
    {
        $payload = ['pre_checkout_query_id' => $preCheckoutQueryId, 'ok' => $ok];
        if ($errorMessage !== null)
            $payload['error_message'] = $errorMessage;
        return $this->request('answerPreCheckoutQuery', $payload);
    }
}
