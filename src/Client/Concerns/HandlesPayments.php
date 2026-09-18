<?php

/**
 * @version 2.2.12
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
     * Create a link for an invoice.
     */
    public function createInvoiceLink(
        string $title,
        string $description,
        string $payloadStr,
        string $currency,
        array $prices,
        ?string $providerToken = null,
        ?int $subscriptionPeriod = null,
        ?array $extraParams = null
    ): Future {
        $payload = [
            'title' => $title,
            'description' => $description,
            'payload' => $payloadStr,
            'currency' => $currency,
            'prices' => json_encode($prices),
        ];
        if ($providerToken !== null) {
            $payload['provider_token'] = $providerToken;
        }
        if ($subscriptionPeriod !== null) {
            $payload['subscription_period'] = $subscriptionPeriod;
        }
        return $this->request('createInvoiceLink', $extraParams ? array_merge($payload, $extraParams) : $payload);
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

    /**
     * Get the current Telegram Stars balance of the bot.
     */
    public function getMyStarBalance(): Future
    {
        return $this->request('getMyStarBalance', []);
    }

    /**
     * Get the bot's Telegram Star transactions in chronological order.
     */
    public function getStarTransactions(?int $offset = null, ?int $limit = null): Future
    {
        $payload = [];
        if ($offset !== null) {
            $payload['offset'] = $offset;
        }
        if ($limit !== null) {
            $payload['limit'] = $limit;
        }
        return $this->request('getStarTransactions', $payload);
    }

    /**
     * Refund a successful payment in Telegram Stars.
     */
    public function refundStarPayment(int $userId, string $telegramPaymentChargeId): Future
    {
        return $this->request('refundStarPayment', [
            'user_id' => $userId,
            'telegram_payment_charge_id' => $telegramPaymentChargeId,
        ]);
    }

    /**
     * Cancel or re-enable extension of a subscription paid in Telegram Stars.
     */
    public function editUserStarSubscription(
        int $userId,
        string $telegramPaymentChargeId,
        bool $isCanceled
    ): Future {
        return $this->request('editUserStarSubscription', [
            'user_id' => $userId,
            'telegram_payment_charge_id' => $telegramPaymentChargeId,
            'is_canceled' => $isCanceled,
        ]);
    }
}
