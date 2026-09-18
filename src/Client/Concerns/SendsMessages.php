<?php

/**
 * @version 2.2.13
 * @author Abolfazl Majidi (Afaz)
 * @package neili
 * @license https://opensource.org/licenses/MIT
 * @link https://github.com/AfazTech/neili
 */

declare(strict_types=1);

namespace Neili\Client\Concerns;

use Amp\Future;

trait SendsMessages
{
    /**
     * Send text message
     */
    public function sendMessage(int $chatId, string $text, ?array $keyboard = null, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'text' => $text];
        if ($keyboard !== null)
            $payload['reply_markup'] = json_encode($keyboard);

        return $this->request('sendMessage', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Reply to a specific message
     */
    public function reply(int $chatId, int $replyToMessageId, string $text, ?array $keyboard = null, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'text' => $text, 'reply_to_message_id' => $replyToMessageId];
        if ($keyboard !== null)
            $payload['reply_markup'] = json_encode($keyboard);
        return $this->request('sendMessage', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Edit existing message text. Set $inlineMessageId to edit an inline
     * message instead of a chat message (in that case $chatId and
     * $messageId may be null).
     */
    public function editMessageText(
        int|string|null $chatId,
        ?int $messageId,
        string $text,
        ?array $keyboard = null,
        ?array $extraParams = null,
        ?string $inlineMessageId = null
    ): Future {
        $payload = ['text' => $text];
        if ($inlineMessageId !== null) {
            $payload['inline_message_id'] = $inlineMessageId;
        } else {
            $payload['chat_id'] = $chatId;
            $payload['message_id'] = $messageId;
        }
        if ($keyboard !== null)
            $payload['reply_markup'] = json_encode($keyboard);
        return $this->request('editMessageText', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Edit the caption of a message.
     */
    public function editMessageCaption(
        int|string|null $chatId,
        ?int $messageId,
        ?string $caption = null,
        ?array $keyboard = null,
        ?array $extraParams = null,
        ?string $inlineMessageId = null
    ): Future {
        $payload = [];

        if ($inlineMessageId !== null) {
            $payload['inline_message_id'] = $inlineMessageId;
        } else {
            $payload['chat_id'] = $chatId;
            $payload['message_id'] = $messageId;
        }

        if ($caption !== null) {
            $payload['caption'] = $caption;
        }

        if ($keyboard !== null) {
            $payload['reply_markup'] = json_encode($keyboard);
        }

        return $this->request('editMessageCaption', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Edit animation, audio, document, live photo, photo, or video messages.
     *
     * @param array $media InputMedia payload
     */
    public function editMessageMedia(
        int|string|null $chatId,
        ?int $messageId,
        array $media,
        ?array $keyboard = null,
        ?array $extraParams = null,
        ?string $inlineMessageId = null
    ): Future {
        $payload = [
            'media' => json_encode($media),
        ];

        if ($inlineMessageId !== null) {
            $payload['inline_message_id'] = $inlineMessageId;
        } else {
            $payload['chat_id'] = $chatId;
            $payload['message_id'] = $messageId;
        }

        if ($keyboard !== null) {
            $payload['reply_markup'] = json_encode($keyboard);
        }

        return $this->request('editMessageMedia', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Edit live location messages until live_period expires.
     */
    public function editMessageLiveLocation(
        int|string|null $chatId,
        ?int $messageId,
        float $latitude,
        float $longitude,
        ?array $extraParams = null,
        ?string $inlineMessageId = null
    ): Future {
        $payload = [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];

        if ($inlineMessageId !== null) {
            $payload['inline_message_id'] = $inlineMessageId;
        } else {
            $payload['chat_id'] = $chatId;
            $payload['message_id'] = $messageId;
        }

        return $this->request('editMessageLiveLocation', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Edit only the reply markup of a message.
     */
    public function editMessageReplyMarkup(
        int|string|null $chatId,
        ?int $messageId,
        ?array $keyboard = null,
        ?array $extraParams = null,
        ?string $inlineMessageId = null
    ): Future {
        $payload = [];

        if ($inlineMessageId !== null) {
            $payload['inline_message_id'] = $inlineMessageId;
        } else {
            $payload['chat_id'] = $chatId;
            $payload['message_id'] = $messageId;
        }

        if ($keyboard !== null) {
            $payload['reply_markup'] = json_encode($keyboard);
        }

        return $this->request('editMessageReplyMarkup', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Delete message
     */
    public function deleteMessage(int $chatId, int $messageId): Future
    {
        return $this->request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
    }

    /**
     * Delete multiple messages simultaneously.
     *
     * @param array<int> $messageIds
     */
    public function deleteMessages(int $chatId, array $messageIds): Future
    {
        return $this->request('deleteMessages', [
            'chat_id' => $chatId,
            'message_ids' => json_encode($messageIds),
        ]);
    }

    /**
     * Forward message from one chat to another
     */
    public function forwardMessage(int $chatId, int $fromChatId, int $messageId, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'from_chat_id' => $fromChatId, 'message_id' => $messageId];
        return $this->request('forwardMessage', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Forward multiple messages of any kind.
     *
     * @param array<int> $messageIds
     */
    public function forwardMessages(
        int $chatId,
        int $fromChatId,
        array $messageIds,
        ?bool $disableNotification = null,
        ?bool $protectContent = null,
        ?array $extraParams = null
    ): Future {
        $payload = [
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_ids' => json_encode($messageIds),
        ];
        if ($disableNotification !== null) {
            $payload['disable_notification'] = $disableNotification;
        }
        if ($protectContent !== null) {
            $payload['protect_content'] = $protectContent;
        }
        return $this->request('forwardMessages', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Copy messages of any kind.
     * Service messages and invoice messages can't be copied.
     */
    public function copyMessage(
        int $chatId,
        int $fromChatId,
        int $messageId,
        ?string $caption = null,
        ?array $keyboard = null,
        ?array $extraParams = null
    ): Future {
        $payload = [
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_id' => $messageId
        ];

        if ($caption !== null) {
            $payload['caption'] = $caption;
        }

        if ($keyboard !== null) {
            $payload['reply_markup'] = json_encode($keyboard);
        }

        return $this->request('copyMessage', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Copy multiple messages of any kind.
     *
     * @param array<int> $messageIds
     */
    public function copyMessages(
        int $chatId,
        int $fromChatId,
        array $messageIds,
        ?bool $disableNotification = null,
        ?bool $protectContent = null,
        ?bool $removeCaption = null,
        ?array $extraParams = null
    ): Future {
        $payload = [
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_ids' => json_encode($messageIds),
        ];
        if ($disableNotification !== null) {
            $payload['disable_notification'] = $disableNotification;
        }
        if ($protectContent !== null) {
            $payload['protect_content'] = $protectContent;
        }
        if ($removeCaption !== null) {
            $payload['remove_caption'] = $removeCaption;
        }
        return $this->request('copyMessages', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Stop updating a live location message before live_period expires.
     */
    public function stopMessageLiveLocation(
        int|string|null $chatId,
        ?int $messageId,
        ?array $keyboard = null,
        ?array $extraParams = null,
        ?string $inlineMessageId = null
    ): Future {
        $payload = [];

        if ($inlineMessageId !== null) {
            $payload['inline_message_id'] = $inlineMessageId;
        } else {
            $payload['chat_id'] = $chatId;
            $payload['message_id'] = $messageId;
        }

        if ($keyboard !== null) {
            $payload['reply_markup'] = json_encode($keyboard);
        }

        return $this->request('stopMessageLiveLocation', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Change the chosen reactions on a message.
     *
     * @param array $reaction Array of ReactionType
     */
    public function setMessageReaction(
        int|string $chatId,
        int $messageId,
        ?array $reaction = null,
        ?bool $isBig = null,
        ?array $extraParams = null
    ): Future {
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ];
        if ($reaction !== null) {
            $payload['reaction'] = json_encode($reaction);
        }
        if ($isBig !== null) {
            $payload['is_big'] = $isBig;
        }
        return $this->request('setMessageReaction', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Remove a reaction from a message in a group or supergroup chat.
     */
    public function deleteMessageReaction(
        int|string $chatId,
        int $messageId,
        ?int $userId = null,
        ?int $actorChatId = null
    ): Future {
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ];
        if ($userId !== null) {
            $payload['user_id'] = $userId;
        }
        if ($actorChatId !== null) {
            $payload['actor_chat_id'] = $actorChatId;
        }
        return $this->request('deleteMessageReaction', $payload);
    }

    /**
     * Remove up to 10000 recent reactions in a group or supergroup chat.
     */
    public function deleteAllMessageReactions(
        int|string $chatId,
        ?int $userId = null,
        ?int $actorChatId = null
    ): Future {
        $payload = ['chat_id' => $chatId];
        if ($userId !== null) {
            $payload['user_id'] = $userId;
        }
        if ($actorChatId !== null) {
            $payload['actor_chat_id'] = $actorChatId;
        }
        return $this->request('deleteAllMessageReactions', $payload);
    }

    /**
     * Send "typing", "upload_photo", etc. action indicator
     */
    public function sendChatAction(int $chatId, string $action): Future
    {
        return $this->request('sendChatAction', ['chat_id' => $chatId, 'action' => $action]);
    }

    /**
     * Send dice animation
     */
    public function sendDice(int $chatId, ?string $emoji = null, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId];
        if ($emoji !== null)
            $payload['emoji'] = $emoji;
        return $this->request('sendDice', $payload + ($extraParams ?? []));
    }

    /**
     * Send poll (quiz or survey)
     */
    public function sendPoll(int $chatId, string $question, array $options, ?bool $isAnonymous = true, ?string $type = 'regular', ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'question' => $question, 'options' => json_encode($options), 'is_anonymous' => $isAnonymous, 'type' => $type];
        return $this->request('sendPoll', $payload + ($extraParams ?? []));
    }

    /**
     * Stop a running poll
     */
    public function stopPoll(int $chatId, int $messageId, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'message_id' => $messageId];
        return $this->request('stopPoll', $payload + ($extraParams ?? []));
    }

    /**
     * Send venue location
     */
    public function sendVenue(int $chatId, float $latitude, float $longitude, string $title, string $address, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'latitude' => $latitude, 'longitude' => $longitude, 'title' => $title, 'address' => $address];
        return $this->request('sendVenue', $payload + ($extraParams ?? []));
    }

    /**
     * Send live location
     */
    public function sendLocation(int $chatId, float $latitude, float $longitude, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'latitude' => $latitude, 'longitude' => $longitude];
        return $this->request('sendLocation', $payload + ($extraParams ?? []));
    }

    /**
     * Send contact info
     */
    public function sendContact(int $chatId, string $phoneNumber, string $firstName, ?string $lastName = null, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'phone_number' => $phoneNumber, 'first_name' => $firstName];
        if ($lastName !== null)
            $payload['last_name'] = $lastName;
        return $this->request('sendContact', $payload + ($extraParams ?? []));
    }
}
