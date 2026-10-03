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

trait SendsMessages
{
    /**
     * Send text message.
     *
     * @param array|null $replyParameters      ReplyParameters payload
     * @param array|null $linkPreviewOptions   LinkPreviewOptions payload
     * @param array|null $keyboard             Reply markup
     */
    public function sendMessage(
        int|string $chatId,
        string $text,
        ?array $keyboard = null,
        ?array $extraParams = null,
        ?int $messageThreadId = null,
        ?int $directMessagesTopicId = null,
        ?array $replyParameters = null,
        ?array $linkPreviewOptions = null,
        ?bool $disableNotification = null,
        ?bool $protectContent = null,
        ?bool $allowPaidBroadcast = null,
        ?string $messageEffectId = null,
        ?string $businessConnectionId = null,
        ?array $suggestedPostParameters = null
    ): Future {
        $payload = ['chat_id' => $chatId, 'text' => $text];

        if ($keyboard !== null) {
            $payload['reply_markup'] = json_encode($keyboard);
        }
        if ($messageThreadId !== null) {
            $payload['message_thread_id'] = $messageThreadId;
        }
        if ($directMessagesTopicId !== null) {
            $payload['direct_messages_topic_id'] = $directMessagesTopicId;
        }
        if ($replyParameters !== null) {
            $payload['reply_parameters'] = json_encode($replyParameters);
        }
        if ($linkPreviewOptions !== null) {
            $payload['link_preview_options'] = json_encode($linkPreviewOptions);
        }
        if ($disableNotification !== null) {
            $payload['disable_notification'] = $disableNotification;
        }
        if ($protectContent !== null) {
            $payload['protect_content'] = $protectContent;
        }
        if ($allowPaidBroadcast !== null) {
            $payload['allow_paid_broadcast'] = $allowPaidBroadcast;
        }
        if ($messageEffectId !== null) {
            $payload['message_effect_id'] = $messageEffectId;
        }
        if ($businessConnectionId !== null) {
            $payload['business_connection_id'] = $businessConnectionId;
        }
        if ($suggestedPostParameters !== null) {
            $payload['suggested_post_parameters'] = json_encode($suggestedPostParameters);
        }

        return $this->request('sendMessage', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Reply to a specific message.
     *
     * Uses ReplyParameters under the hood, matching current Bot API rules.
     */
    public function reply(
        int|string $chatId,
        int $replyToMessageId,
        string $text,
        ?array $keyboard = null,
        ?array $extraParams = null
    ): Future {
        $replyParameters = ['message_id' => $replyToMessageId];

        return $this->sendMessage(
            $chatId,
            $text,
            $keyboard,
            $extraParams,
            replyParameters: $replyParameters,
        );
    }

    /**
     * Edit existing message text.
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
     */
    public function editMessageMedia(
        int|string|null $chatId,
        ?int $messageId,
        array $media,
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

        $attachments = [];
        $normalizedMedia = $this->extractMediaAttachments($media, $attachments);
        $payload['media'] = json_encode($normalizedMedia);

        if ($extraParams !== null) {
            $payload = array_merge($payload, $extraParams);
        }

        return $attachments
            ? $this->requestWithFile('editMessageMedia', $payload, $attachments)
            : $this->request('editMessageMedia', $payload);
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
     * Delete message.
     */
    public function deleteMessage(int|string $chatId, int $messageId): Future
    {
        return $this->request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
    }

    /**
     * Delete multiple messages simultaneously.
     */
    public function deleteMessages(int|string $chatId, array $messageIds): Future
    {
        return $this->request('deleteMessages', [
            'chat_id' => $chatId,
            'message_ids' => json_encode($messageIds),
        ]);
    }

    /**
     * Forward message from one chat to another.
     */
    public function forwardMessage(
        int|string $chatId,
        int|string $fromChatId,
        int $messageId,
        ?int $videoStartTimestamp = null,
        ?array $extraParams = null
    ): Future {
        $payload = [
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_id' => $messageId,
        ];
        if ($videoStartTimestamp !== null) {
            $payload['video_start_timestamp'] = $videoStartTimestamp;
        }
        return $this->request('forwardMessage', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Forward multiple messages of any kind.
     */
    public function forwardMessages(
        int|string $chatId,
        int|string $fromChatId,
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
     */
    public function copyMessage(
        int|string $chatId,
        int|string $fromChatId,
        int $messageId,
        ?string $caption = null,
        ?array $keyboard = null,
        ?array $extraParams = null
    ): Future {
        $payload = [
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_id' => $messageId,
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
     */
    public function copyMessages(
        int|string $chatId,
        int|string $fromChatId,
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
     * Send "typing", "upload_photo", etc. action indicator.
     */
    public function sendChatAction(int|string $chatId, string $action): Future
    {
        return $this->request('sendChatAction', ['chat_id' => $chatId, 'action' => $action]);
    }

    /**
     * Send dice animation.
     */
    public function sendDice(int|string $chatId, ?string $emoji = null, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId];
        if ($emoji !== null)
            $payload['emoji'] = $emoji;
        return $this->request('sendDice', $payload + ($extraParams ?? []));
    }

    /**
     * Send poll (quiz or survey).
     *
     * Each option may be a plain string, an InputPollOption array, or an
     * array containing a local Media object inside its 'media' field. All
     * media fields (per-option, poll description, quiz explanation) are
     * normalized and their local files uploaded via multipart/form-data.
     *
     * @param array $options  List of poll options
     * @param array|null $media               InputPollMedia for the poll description
     * @param array|null $explanationMedia    InputPollMedia for the quiz explanation
     */
    public function sendPoll(
        int|string $chatId,
        string $question,
        array $options,
        ?bool $isAnonymous = null,
        ?string $type = null,
        ?array $extraParams = null,
        ?array $questionEntities = null,
        ?array $media = null,
        ?string $description = null,
        ?array $descriptionEntities = null,
        ?string $explanation = null,
        ?array $explanationEntities = null,
        ?array $explanationMedia = null
    ): Future {
        $attachments = [];
        $normalizedOptions = [];

        foreach ($options as $index => $option) {
            if (is_string($option)) {
                $normalizedOptions[] = ['text' => $option];
                continue;
            }

            if (!is_array($option) || !isset($option['text'])) {
                throw new InvalidArgumentException(
                    'Each poll option must be a string or an InputPollOption array containing a "text" key.'
                );
            }

            if (isset($option['media']) && is_array($option['media'])) {
                $option['media'] = $this->extractPollMediaAttachments(
                    $option['media'],
                    $attachments,
                    'option_' . $index
                );
            }

            $normalizedOptions[] = $option;
        }

        $payload = [
            'chat_id' => $chatId,
            'question' => $question,
            'options' => json_encode($normalizedOptions),
        ];

        if ($isAnonymous !== null) {
            $payload['is_anonymous'] = $isAnonymous;
        }
        if ($type !== null) {
            $payload['type'] = $type;
        }
        if ($questionEntities !== null) {
            $payload['question_entities'] = json_encode($questionEntities);
        }
        if ($media !== null) {
            $payload['media'] = json_encode(
                $this->extractPollMediaAttachments($media, $attachments, 'poll_media')
            );
        }
        if ($description !== null) {
            $payload['description'] = $description;
        }
        if ($descriptionEntities !== null) {
            $payload['description_entities'] = json_encode($descriptionEntities);
        }
        if ($explanation !== null) {
            $payload['explanation'] = $explanation;
        }
        if ($explanationEntities !== null) {
            $payload['explanation_entities'] = json_encode($explanationEntities);
        }
        if ($explanationMedia !== null) {
            $payload['explanation_media'] = json_encode(
                $this->extractPollMediaAttachments($explanationMedia, $attachments, 'explanation_media')
            );
        }

        if ($extraParams !== null) {
            $payload += $extraParams;
        }

        return $attachments
            ? $this->requestWithFile('sendPoll', $payload, $attachments)
            : $this->request('sendPoll', $payload);
    }

    /**
     * Stop a running poll.
     */
    public function stopPoll(int|string $chatId, int $messageId, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'message_id' => $messageId];
        return $this->request('stopPoll', $payload + ($extraParams ?? []));
    }

    /**
     * Send venue location.
     */
    public function sendVenue(
        int|string $chatId,
        float $latitude,
        float $longitude,
        string $title,
        string $address,
        ?string $foursquareId = null,
        ?string $foursquareType = null,
        ?string $googlePlaceId = null,
        ?string $googlePlaceType = null,
        ?array $extraParams = null
    ): Future {
        $payload = [
            'chat_id' => $chatId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'title' => $title,
            'address' => $address,
        ];
        if ($foursquareId !== null) {
            $payload['foursquare_id'] = $foursquareId;
        }
        if ($foursquareType !== null) {
            $payload['foursquare_type'] = $foursquareType;
        }
        if ($googlePlaceId !== null) {
            $payload['google_place_id'] = $googlePlaceId;
        }
        if ($googlePlaceType !== null) {
            $payload['google_place_type'] = $googlePlaceType;
        }
        return $this->request('sendVenue', $payload + ($extraParams ?? []));
    }

    /**
     * Send live location.
     */
    public function sendLocation(int|string $chatId, float $latitude, float $longitude, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'latitude' => $latitude, 'longitude' => $longitude];
        return $this->request('sendLocation', $payload + ($extraParams ?? []));
    }

    /**
     * Send contact info.
     */
    public function sendContact(
        int|string $chatId,
        string $phoneNumber,
        string $firstName,
        ?string $lastName = null,
        ?string $vcard = null,
        ?array $extraParams = null
    ): Future {
        $payload = [
            'chat_id' => $chatId,
            'phone_number' => $phoneNumber,
            'first_name' => $firstName,
        ];
        if ($lastName !== null) {
            $payload['last_name'] = $lastName;
        }
        if ($vcard !== null) {
            $payload['vcard'] = $vcard;
        }
        return $this->request('sendContact', $payload + ($extraParams ?? []));
    }
}
