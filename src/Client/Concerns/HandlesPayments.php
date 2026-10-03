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

trait HandlesPayments
{
    /**
     * Send an invoice for a payment.
     *
     * For Telegram Stars subscriptions pass $subscriptionPeriod = 2592000
     * (30 days) and use currency "XTR" with an empty $providerToken.
     *
     * @param string      $providerToken   Empty string for Telegram Stars
     * @param array       $prices          Array of LabeledPrice
     * @param int|null    $subscriptionPeriod  30 days in seconds for Star subscriptions
     */
    public function sendInvoice(
        int|string $chatId,
        string $title,
        string $description,
        string $payloadStr,
        string $providerToken,
        string $currency,
        array $prices,
        ?array $extraParams = null,
        ?int $subscriptionPeriod = null,
        ?int $messageThreadId = null,
        ?int $directMessagesTopicId = null,
        ?int $maxTipAmount = null,
        ?array $suggestedTipAmounts = null,
        ?string $startParameter = null,
        ?string $providerData = null,
        ?string $photoUrl = null,
        ?int $photoSize = null,
        ?int $photoWidth = null,
        ?int $photoHeight = null,
        ?bool $needName = null,
        ?bool $needPhoneNumber = null,
        ?bool $needEmail = null,
        ?bool $needShippingAddress = null,
        ?bool $sendPhoneNumberToProvider = null,
        ?bool $sendEmailToProvider = null,
        ?bool $isFlexible = null,
        ?bool $disableNotification = null,
        ?bool $protectContent = null,
        ?bool $allowPaidBroadcast = null,
        ?string $messageEffectId = null,
        ?array $suggestedPostParameters = null,
        ?array $replyParameters = null,
        ?array $keyboard = null
    ): Future {
        $payload = [
            'chat_id' => $chatId,
            'title' => $title,
            'description' => $description,
            'payload' => $payloadStr,
            'provider_token' => $providerToken,
            'currency' => $currency,
            'prices' => json_encode($prices),
        ];

        if ($subscriptionPeriod !== null)
            $payload['subscription_period'] = $subscriptionPeriod;
        if ($messageThreadId !== null)
            $payload['message_thread_id'] = $messageThreadId;
        if ($directMessagesTopicId !== null)
            $payload['direct_messages_topic_id'] = $directMessagesTopicId;
        if ($maxTipAmount !== null)
            $payload['max_tip_amount'] = $maxTipAmount;
        if ($suggestedTipAmounts !== null)
            $payload['suggested_tip_amounts'] = json_encode($suggestedTipAmounts);
        if ($startParameter !== null)
            $payload['start_parameter'] = $startParameter;
        if ($providerData !== null)
            $payload['provider_data'] = $providerData;
        if ($photoUrl !== null)
            $payload['photo_url'] = $photoUrl;
        if ($photoSize !== null)
            $payload['photo_size'] = $photoSize;
        if ($photoWidth !== null)
            $payload['photo_width'] = $photoWidth;
        if ($photoHeight !== null)
            $payload['photo_height'] = $photoHeight;
        if ($needName !== null)
            $payload['need_name'] = $needName;
        if ($needPhoneNumber !== null)
            $payload['need_phone_number'] = $needPhoneNumber;
        if ($needEmail !== null)
            $payload['need_email'] = $needEmail;
        if ($needShippingAddress !== null)
            $payload['need_shipping_address'] = $needShippingAddress;
        if ($sendPhoneNumberToProvider !== null)
            $payload['send_phone_number_to_provider'] = $sendPhoneNumberToProvider;
        if ($sendEmailToProvider !== null)
            $payload['send_email_to_provider'] = $sendEmailToProvider;
        if ($isFlexible !== null)
            $payload['is_flexible'] = $isFlexible;
        if ($disableNotification !== null)
            $payload['disable_notification'] = $disableNotification;
        if ($protectContent !== null)
            $payload['protect_content'] = $protectContent;
        if ($allowPaidBroadcast !== null)
            $payload['allow_paid_broadcast'] = $allowPaidBroadcast;
        if ($messageEffectId !== null)
            $payload['message_effect_id'] = $messageEffectId;
        if ($suggestedPostParameters !== null)
            $payload['suggested_post_parameters'] = json_encode($suggestedPostParameters);
        if ($replyParameters !== null)
            $payload['reply_parameters'] = json_encode($replyParameters);
        if ($keyboard !== null)
            $payload['reply_markup'] = json_encode($keyboard);

        return $this->request('sendInvoice', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Create a link for an invoice.
     *
     * @param array $prices Array of LabeledPrice
     */
    public function createInvoiceLink(
        string $title,
        string $description,
        string $payloadStr,
        string $currency,
        array $prices,
        ?string $providerToken = null,
        ?int $subscriptionPeriod = null,
        ?array $extraParams = null,
        ?int $maxTipAmount = null,
        ?array $suggestedTipAmounts = null,
        ?string $providerData = null,
        ?string $photoUrl = null,
        ?int $photoSize = null,
        ?int $photoWidth = null,
        ?int $photoHeight = null,
        ?bool $needName = null,
        ?bool $needPhoneNumber = null,
        ?bool $needEmail = null,
        ?bool $needShippingAddress = null,
        ?bool $sendPhoneNumberToProvider = null,
        ?bool $sendEmailToProvider = null,
        ?bool $isFlexible = null,
        ?string $businessConnectionId = null
    ): Future {
        $payload = [
            'title' => $title,
            'description' => $description,
            'payload' => $payloadStr,
            'currency' => $currency,
            'prices' => json_encode($prices),
        ];
        if ($providerToken !== null)
            $payload['provider_token'] = $providerToken;
        if ($subscriptionPeriod !== null)
            $payload['subscription_period'] = $subscriptionPeriod;
        if ($maxTipAmount !== null)
            $payload['max_tip_amount'] = $maxTipAmount;
        if ($suggestedTipAmounts !== null)
            $payload['suggested_tip_amounts'] = json_encode($suggestedTipAmounts);
        if ($providerData !== null)
            $payload['provider_data'] = $providerData;
        if ($photoUrl !== null)
            $payload['photo_url'] = $photoUrl;
        if ($photoSize !== null)
            $payload['photo_size'] = $photoSize;
        if ($photoWidth !== null)
            $payload['photo_width'] = $photoWidth;
        if ($photoHeight !== null)
            $payload['photo_height'] = $photoHeight;
        if ($needName !== null)
            $payload['need_name'] = $needName;
        if ($needPhoneNumber !== null)
            $payload['need_phone_number'] = $needPhoneNumber;
        if ($needEmail !== null)
            $payload['need_email'] = $needEmail;
        if ($needShippingAddress !== null)
            $payload['need_shipping_address'] = $needShippingAddress;
        if ($sendPhoneNumberToProvider !== null)
            $payload['send_phone_number_to_provider'] = $sendPhoneNumberToProvider;
        if ($sendEmailToProvider !== null)
            $payload['send_email_to_provider'] = $sendEmailToProvider;
        if ($isFlexible !== null)
            $payload['is_flexible'] = $isFlexible;
        if ($businessConnectionId !== null)
            $payload['business_connection_id'] = $businessConnectionId;

        return $this->request('createInvoiceLink', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Answer shipping query.
     */
    public function answerShippingQuery(
        string $shippingQueryId,
        bool $ok,
        ?array $shippingOptions = null,
        ?string $errorMessage = null
    ): Future {
        $payload = ['shipping_query_id' => $shippingQueryId, 'ok' => $ok];
        if ($shippingOptions !== null)
            $payload['shipping_options'] = json_encode($shippingOptions);
        if ($errorMessage !== null)
            $payload['error_message'] = $errorMessage;
        return $this->request('answerShippingQuery', $payload);
    }

    /**
     * Answer pre-checkout query.
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
        if ($offset !== null)
            $payload['offset'] = $offset;
        if ($limit !== null)
            $payload['limit'] = $limit;
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
