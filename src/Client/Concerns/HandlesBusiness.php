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

trait HandlesBusiness
{
    /**
     * Get information about the connection of the bot with a business account.
     */
    public function getBusinessConnection(string $businessConnectionId): Future
    {
        return $this->request('getBusinessConnection', [
            'business_connection_id' => $businessConnectionId,
        ]);
    }

    /**
     * Mark an incoming message as read on behalf of a business account.
     */
    public function readBusinessMessage(
        string $businessConnectionId,
        int $chatId,
        int $messageId
    ): Future {
        return $this->request('readBusinessMessage', [
            'business_connection_id' => $businessConnectionId,
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    /**
     * Delete messages on behalf of a business account.
     *
     * @param array<int> $messageIds
     */
    public function deleteBusinessMessages(
        string $businessConnectionId,
        array $messageIds
    ): Future {
        return $this->request('deleteBusinessMessages', [
            'business_connection_id' => $businessConnectionId,
            'message_ids' => json_encode($messageIds),
        ]);
    }

    /**
     * Change the first and last name of a managed business account.
     */
    public function setBusinessAccountName(
        string $businessConnectionId,
        string $firstName,
        ?string $lastName = null
    ): Future {
        $payload = [
            'business_connection_id' => $businessConnectionId,
            'first_name' => $firstName,
        ];
        if ($lastName !== null) {
            $payload['last_name'] = $lastName;
        }
        return $this->request('setBusinessAccountName', $payload);
    }

    /**
     * Change the username of a managed business account.
     */
    public function setBusinessAccountUsername(
        string $businessConnectionId,
        ?string $username = null
    ): Future {
        $payload = ['business_connection_id' => $businessConnectionId];
        if ($username !== null) {
            $payload['username'] = $username;
        }
        return $this->request('setBusinessAccountUsername', $payload);
    }

    /**
     * Change the bio of a managed business account.
     */
    public function setBusinessAccountBio(
        string $businessConnectionId,
        ?string $bio = null
    ): Future {
        $payload = ['business_connection_id' => $businessConnectionId];
        if ($bio !== null) {
            $payload['bio'] = $bio;
        }
        return $this->request('setBusinessAccountBio', $payload);
    }

    /**
     * Change the profile photo of a managed business account.
     *
     * @param array $photo InputProfilePhoto payload
     */
    public function setBusinessAccountProfilePhoto(
        string $businessConnectionId,
        array $photo,
        ?bool $isPublic = null
    ): Future {
        $payload = [
            'business_connection_id' => $businessConnectionId,
            'photo' => json_encode($photo),
        ];
        if ($isPublic !== null) {
            $payload['is_public'] = $isPublic;
        }
        return $this->request('setBusinessAccountProfilePhoto', $payload);
    }

    /**
     * Remove the current profile photo of a managed business account.
     */
    public function removeBusinessAccountProfilePhoto(
        string $businessConnectionId,
        ?bool $isPublic = null
    ): Future {
        $payload = ['business_connection_id' => $businessConnectionId];
        if ($isPublic !== null) {
            $payload['is_public'] = $isPublic;
        }
        return $this->request('removeBusinessAccountProfilePhoto', $payload);
    }

    /**
     * Change the privacy settings pertaining to incoming gifts in a managed business account.
     *
     * @param array $acceptedGiftTypes AcceptedGiftTypes payload
     */
    public function setBusinessAccountGiftSettings(
        string $businessConnectionId,
        bool $showGiftButton,
        array $acceptedGiftTypes
    ): Future {
        return $this->request('setBusinessAccountGiftSettings', [
            'business_connection_id' => $businessConnectionId,
            'show_gift_button' => $showGiftButton,
            'accepted_gift_types' => json_encode($acceptedGiftTypes),
        ]);
    }

    /**
     * Return the amount of Telegram Stars owned by a managed business account.
     */
    public function getBusinessAccountStarBalance(string $businessConnectionId): Future
    {
        return $this->request('getBusinessAccountStarBalance', [
            'business_connection_id' => $businessConnectionId,
        ]);
    }

    /**
     * Transfer Telegram Stars from the business account balance to the bot's balance.
     */
    public function transferBusinessAccountStars(
        string $businessConnectionId,
        int $starCount
    ): Future {
        return $this->request('transferBusinessAccountStars', [
            'business_connection_id' => $businessConnectionId,
            'star_count' => $starCount,
        ]);
    }

    /**
     * Return the gifts received and owned by a managed business account.
     */
    public function getBusinessAccountGifts(
        string $businessConnectionId,
        array $options = []
    ): Future {
        return $this->request('getBusinessAccountGifts', array_merge(
            ['business_connection_id' => $businessConnectionId],
            $options
        ));
    }

    /**
     * Convert a given regular gift to Telegram Stars.
     */
    public function convertGiftToStars(string $businessConnectionId, string $ownedGiftId): Future
    {
        return $this->request('convertGiftToStars', [
            'business_connection_id' => $businessConnectionId,
            'owned_gift_id' => $ownedGiftId,
        ]);
    }

    /**
     * Upgrade a given regular gift to a unique gift.
     */
    public function upgradeGift(
        string $businessConnectionId,
        string $ownedGiftId,
        ?bool $keepOriginalDetails = null,
        ?int $starCount = null
    ): Future {
        $payload = [
            'business_connection_id' => $businessConnectionId,
            'owned_gift_id' => $ownedGiftId,
        ];
        if ($keepOriginalDetails !== null) {
            $payload['keep_original_details'] = $keepOriginalDetails;
        }
        if ($starCount !== null) {
            $payload['star_count'] = $starCount;
        }
        return $this->request('upgradeGift', $payload);
    }

    /**
     * Transfer an owned unique gift to another user.
     */
    public function transferGift(
        string $businessConnectionId,
        string $ownedGiftId,
        int $newOwnerChatId,
        ?int $starCount = null
    ): Future {
        $payload = [
            'business_connection_id' => $businessConnectionId,
            'owned_gift_id' => $ownedGiftId,
            'new_owner_chat_id' => $newOwnerChatId,
        ];
        if ($starCount !== null) {
            $payload['star_count'] = $starCount;
        }
        return $this->request('transferGift', $payload);
    }

    /**
     * Send a checklist on behalf of a connected business account.
     *
     * @param array $checklist InputChecklist payload
     */
    public function sendChecklist(
        string $businessConnectionId,
        int|string $chatId,
        array $checklist,
        ?bool $disableNotification = null,
        ?bool $protectContent = null,
        ?string $messageEffectId = null,
        ?array $replyParameters = null,
        ?array $replyMarkup = null,
        ?array $extraParams = null
    ): Future {
        $payload = [
            'business_connection_id' => $businessConnectionId,
            'chat_id' => $chatId,
            'checklist' => json_encode($checklist),
        ];
        if ($disableNotification !== null) {
            $payload['disable_notification'] = $disableNotification;
        }
        if ($protectContent !== null) {
            $payload['protect_content'] = $protectContent;
        }
        if ($messageEffectId !== null) {
            $payload['message_effect_id'] = $messageEffectId;
        }
        if ($replyParameters !== null) {
            $payload['reply_parameters'] = json_encode($replyParameters);
        }
        if ($replyMarkup !== null) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }
        return $this->request('sendChecklist', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Edit a checklist on behalf of a connected business account.
     *
     * @param array $checklist InputChecklist payload
     */
    public function editMessageChecklist(
        string $businessConnectionId,
        int|string $chatId,
        int $messageId,
        array $checklist,
        ?array $replyMarkup = null,
        ?array $extraParams = null
    ): Future {
        $payload = [
            'business_connection_id' => $businessConnectionId,
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'checklist' => json_encode($checklist),
        ];
        if ($replyMarkup !== null) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }
        return $this->request('editMessageChecklist', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }
}
