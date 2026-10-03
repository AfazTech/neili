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
use InvalidArgumentException;

trait HandlesGifts
{
    /**
     * Return the list of gifts that can be sent by the bot to users and channel chats.
     */
    public function getAvailableGifts(): Future
    {
        return $this->request('getAvailableGifts', []);
    }

    /**
     * Send a gift to the given user or channel chat.
     *
     * Exactly one of $userId or $chatId must be provided.
     *
     * @param int|null    $userId
     * @param int|string|null $chatId
     * @param string      $giftId
     * @param bool|null   $payForUpgrade
     * @param string|null $text
     * @param string|null $textParseMode
     * @param array|null  $textEntities
     * @param array|null  $extraParams
     */
    public function sendGift(
        ?int $userId,
        int|string|null $chatId,
        string $giftId,
        ?bool $payForUpgrade = null,
        ?string $text = null,
        ?string $textParseMode = null,
        ?array $textEntities = null,
        ?array $extraParams = null
    ): Future {
        if (($userId === null) === ($chatId === null)) {
            throw new InvalidArgumentException(
                'Exactly one of user_id or chat_id must be provided when sending a gift.'
            );
        }

        $payload = ['gift_id' => $giftId];

        if ($userId !== null) {
            $payload['user_id'] = $userId;
        }
        if ($chatId !== null) {
            $payload['chat_id'] = $chatId;
        }
        if ($payForUpgrade !== null) {
            $payload['pay_for_upgrade'] = $payForUpgrade;
        }
        if ($text !== null) {
            $payload['text'] = $text;
        }
        if ($textParseMode !== null) {
            $payload['text_parse_mode'] = $textParseMode;
        }
        if ($textEntities !== null) {
            $payload['text_entities'] = json_encode($textEntities);
        }

        return $this->request('sendGift', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Gift a Telegram Premium subscription to the given user.
     */
    public function giftPremiumSubscription(
        int $userId,
        int $monthCount,
        int $starCount,
        ?string $text = null,
        ?string $textParseMode = null,
        ?array $textEntities = null,
        ?array $extraParams = null
    ): Future {
        $payload = [
            'user_id' => $userId,
            'month_count' => $monthCount,
            'star_count' => $starCount,
        ];
        if ($text !== null) {
            $payload['text'] = $text;
        }
        if ($textParseMode !== null) {
            $payload['text_parse_mode'] = $textParseMode;
        }
        if ($textEntities !== null) {
            $payload['text_entities'] = json_encode($textEntities);
        }
        return $this->request('giftPremiumSubscription', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Returns the gifts owned and hosted by a user.
     */
    public function getUserGifts(int $userId, array $options = []): Future
    {
        return $this->request('getUserGifts', array_merge(['user_id' => $userId], $options));
    }

    /**
     * Returns the gifts owned by a chat.
     */
    public function getChatGifts(int|string $chatId, array $options = []): Future
    {
        return $this->request('getChatGifts', array_merge(['chat_id' => $chatId], $options));
    }
}
