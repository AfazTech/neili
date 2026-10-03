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
        ?array $suggestedPostParameters = null,
        ?string $parseMode = null,
        ?array $entities = null,
        ?array $ephemeralMessageParameters = null
    ): Future {
        $payload = ['chat_id' => $chatId, 'text' => $text];

        if ($keyboard !== null)
            $payload['reply_markup'] = json_encode($keyboard);
        if ($messageThreadId !== null)
            $payload['message_thread_id'] = $messageThreadId;
        if ($directMessagesTopicId !== null)
            $payload['direct_messages_topic_id'] = $directMessagesTopicId;
        if ($replyParameters !== null)
            $payload['reply_parameters'] = json_encode($replyParameters);
        if ($linkPreviewOptions !== null)
            $payload['link_preview_options'] = json_encode($linkPreviewOptions);
        if ($disableNotification !== null)
            $payload['disable_notification'] = $disableNotification;
        if ($protectContent !== null)
            $payload['protect_content'] = $protectContent;
        if ($allowPaidBroadcast !== null)
            $payload['allow_paid_broadcast'] = $allowPaidBroadcast;
        if ($messageEffectId !== null)
            $payload['message_effect_id'] = $messageEffectId;
        if ($businessConnectionId !== null)
            $payload['business_connection_id'] = $businessConnectionId;
        if ($suggestedPostParameters !== null)
            $payload['suggested_post_parameters'] = json_encode($suggestedPostParameters);
        if ($parseMode !== null)
            $payload['parse_mode'] = $parseMode;
        if ($entities !== null)
            $payload['entities'] = json_encode($entities);
        if ($ephemeralMessageParameters !== null)
            $payload['ephemeral_message_parameters'] = json_encode($ephemeralMessageParameters);

        return $this->request('sendMessage', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Reply to a specific message using ReplyParameters.
     */
    public function reply(
        int|string $chatId,
        int $replyToMessageId,
        string $text,
        ?array $keyboard = null,
        ?array $extraParams = null
    ): Future {
        return $this->sendMessage(
            $chatId,
            $text,
            $keyboard,
            $extraParams,
            replyParameters: ['message_id' => $replyToMessageId],
        );
    }

    /**
     * Edit existing message text.
     */
    public function editMessageText(
        int|string|null $chatId,
        ?int $messageId,
        ?string $text = null,
        ?array $keyboard = null,
        ?array $extraParams = null,
        ?string $inlineMessageId = null,
        ?array $richMessage = null,
        ?string $parseMode = null,
        ?array $entities = null,
        ?array $linkPreviewOptions = null,
        ?string $businessConnectionId = null
    ): Future {
        if ($text === null && $richMessage === null) {
            throw new InvalidArgumentException('Either text or rich_message must be provided.');
        }

        $payload = [];

        if ($inlineMessageId !== null) {
            $payload['inline_message_id'] = $inlineMessageId;
        } else {
            $payload['chat_id'] = $chatId;
            $payload['message_id'] = $messageId;
        }

        if ($text !== null)
            $payload['text'] = $text;
        if ($richMessage !== null)
            $payload['rich_message'] = json_encode($richMessage);
        if ($parseMode !== null)
            $payload['parse_mode'] = $parseMode;
        if ($entities !== null)
            $payload['entities'] = json_encode($entities);
        if ($linkPreviewOptions !== null)
            $payload['link_preview_options'] = json_encode($linkPreviewOptions);
        if ($keyboard !== null)
            $payload['reply_markup'] = json_encode($keyboard);
        if ($businessConnectionId !== null)
            $payload['business_connection_id'] = $businessConnectionId;

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
        ?string $inlineMessageId = null,
        ?string $parseMode = null,
        ?array $captionEntities = null,
        ?bool $showCaptionAboveMedia = null
    ): Future {
        $payload = [];

        if ($inlineMessageId !== null) {
            $payload['inline_message_id'] = $inlineMessageId;
        } else {
            $payload['chat_id'] = $chatId;
            $payload['message_id'] = $messageId;
        }

        if ($caption !== null)
            $payload['caption'] = $caption;
        if ($parseMode !== null)
            $payload['parse_mode'] = $parseMode;
        if ($captionEntities !== null)
            $payload['caption_entities'] = json_encode($captionEntities);
        if ($showCaptionAboveMedia !== null)
            $payload['show_caption_above_media'] = $showCaptionAboveMedia;
        if ($keyboard !== null)
            $payload['reply_markup'] = json_encode($keyboard);

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
        ?string $inlineMessageId = null,
        ?float $horizontalAccuracy = null,
        ?int $heading = null,
        ?int $proximityAlertRadius = null,
        ?int $livePeriod = null,
        ?array $keyboard = null,
        ?string $businessConnectionId = null
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

        if ($horizontalAccuracy !== null)
            $payload['horizontal_accuracy'] = $horizontalAccuracy;
        if ($heading !== null)
            $payload['heading'] = $heading;
        if ($proximityAlertRadius !== null)
            $payload['proximity_alert_radius'] = $proximityAlertRadius;
        if ($livePeriod !== null)
            $payload['live_period'] = $livePeriod;
        if ($keyboard !== null)
            $payload['reply_markup'] = json_encode($keyboard);
        if ($businessConnectionId !== null)
            $payload['business_connection_id'] = $businessConnectionId;

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

        if ($keyboard !== null)
            $payload['reply_markup'] = json_encode($keyboard);

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
     *
     * The $videoStartTimestamp parameter is placed AFTER $extraParams to
     * preserve the original 4-argument signature:
     * forwardMessage($chatId, $fromChatId, $messageId, ?array $extraParams).
     */
    public function forwardMessage(
        int|string $chatId,
        int|string $fromChatId,
        int $messageId,
        ?array $extraParams = null,
        ?int $videoStartTimestamp = null,
        ?int $messageThreadId = null,
        ?int $directMessagesTopicId = null,
        ?bool $disableNotification = null,
        ?bool $protectContent = null,
        ?string $messageEffectId = null,
        ?array $suggestedPostParameters = null
    ): Future {
        $payload = [
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_id' => $messageId,
        ];
        if ($videoStartTimestamp !== null)
            $payload['video_start_timestamp'] = $videoStartTimestamp;
        if ($messageThreadId !== null)
            $payload['message_thread_id'] = $messageThreadId;
        if ($directMessagesTopicId !== null)
            $payload['direct_messages_topic_id'] = $directMessagesTopicId;
        if ($disableNotification !== null)
            $payload['disable_notification'] = $disableNotification;
        if ($protectContent !== null)
            $payload['protect_content'] = $protectContent;
        if ($messageEffectId !== null)
            $payload['message_effect_id'] = $messageEffectId;
        if ($suggestedPostParameters !== null)
            $payload['suggested_post_parameters'] = json_encode($suggestedPostParameters);

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
        ?array $extraParams = null,
        ?int $messageThreadId = null,
        ?int $directMessagesTopicId = null
    ): Future {
        $payload = [
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_ids' => json_encode($messageIds),
        ];
        if ($disableNotification !== null)
            $payload['disable_notification'] = $disableNotification;
        if ($protectContent !== null)
            $payload['protect_content'] = $protectContent;
        if ($messageThreadId !== null)
            $payload['message_thread_id'] = $messageThreadId;
        if ($directMessagesTopicId !== null)
            $payload['direct_messages_topic_id'] = $directMessagesTopicId;

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
        ?array $extraParams = null,
        ?string $parseMode = null,
        ?array $captionEntities = null,
        ?bool $showCaptionAboveMedia = null,
        ?int $videoStartTimestamp = null,
        ?int $messageThreadId = null,
        ?int $directMessagesTopicId = null,
        ?bool $disableNotification = null,
        ?bool $protectContent = null,
        ?bool $allowPaidBroadcast = null,
        ?string $messageEffectId = null,
        ?array $replyParameters = null,
        ?array $suggestedPostParameters = null
    ): Future {
        $payload = [
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_id' => $messageId,
        ];

        if ($caption !== null)
            $payload['caption'] = $caption;
        if ($parseMode !== null)
            $payload['parse_mode'] = $parseMode;
        if ($captionEntities !== null)
            $payload['caption_entities'] = json_encode($captionEntities);
        if ($showCaptionAboveMedia !== null)
            $payload['show_caption_above_media'] = $showCaptionAboveMedia;
        if ($videoStartTimestamp !== null)
            $payload['video_start_timestamp'] = $videoStartTimestamp;
        if ($keyboard !== null)
            $payload['reply_markup'] = json_encode($keyboard);
        if ($messageThreadId !== null)
            $payload['message_thread_id'] = $messageThreadId;
        if ($directMessagesTopicId !== null)
            $payload['direct_messages_topic_id'] = $directMessagesTopicId;
        if ($disableNotification !== null)
            $payload['disable_notification'] = $disableNotification;
        if ($protectContent !== null)
            $payload['protect_content'] = $protectContent;
        if ($allowPaidBroadcast !== null)
            $payload['allow_paid_broadcast'] = $allowPaidBroadcast;
        if ($messageEffectId !== null)
            $payload['message_effect_id'] = $messageEffectId;
        if ($replyParameters !== null)
            $payload['reply_parameters'] = json_encode($replyParameters);
        if ($suggestedPostParameters !== null)
            $payload['suggested_post_parameters'] = json_encode($suggestedPostParameters);

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
        ?array $extraParams = null,
        ?int $messageThreadId = null,
        ?int $directMessagesTopicId = null
    ): Future {
        $payload = [
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_ids' => json_encode($messageIds),
        ];
        if ($disableNotification !== null)
            $payload['disable_notification'] = $disableNotification;
        if ($protectContent !== null)
            $payload['protect_content'] = $protectContent;
        if ($removeCaption !== null)
            $payload['remove_caption'] = $removeCaption;
        if ($messageThreadId !== null)
            $payload['message_thread_id'] = $messageThreadId;
        if ($directMessagesTopicId !== null)
            $payload['direct_messages_topic_id'] = $directMessagesTopicId;

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
        ?string $inlineMessageId = null,
        ?string $businessConnectionId = null
    ): Future {
        $payload = [];

        if ($inlineMessageId !== null) {
            $payload['inline_message_id'] = $inlineMessageId;
        } else {
            $payload['chat_id'] = $chatId;
            $payload['message_id'] = $messageId;
        }

        if ($keyboard !== null)
            $payload['reply_markup'] = json_encode($keyboard);
        if ($businessConnectionId !== null)
            $payload['business_connection_id'] = $businessConnectionId;

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
        if ($reaction !== null)
            $payload['reaction'] = json_encode($reaction);
        if ($isBig !== null)
            $payload['is_big'] = $isBig;
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
        if ($userId !== null)
            $payload['user_id'] = $userId;
        if ($actorChatId !== null)
            $payload['actor_chat_id'] = $actorChatId;
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
        if ($userId !== null)
            $payload['user_id'] = $userId;
        if ($actorChatId !== null)
            $payload['actor_chat_id'] = $actorChatId;
        return $this->request('deleteAllMessageReactions', $payload);
    }

    /**
     * Send "typing", "upload_photo", etc. action indicator.
     */
    public function sendChatAction(
        int|string $chatId,
        string $action,
        ?array $extraParams = null,
        ?int $messageThreadId = null,
        ?string $businessConnectionId = null
    ): Future {
        $payload = ['chat_id' => $chatId, 'action' => $action];
        if ($messageThreadId !== null)
            $payload['message_thread_id'] = $messageThreadId;
        if ($businessConnectionId !== null)
            $payload['business_connection_id'] = $businessConnectionId;
        return $this->request('sendChatAction', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Send dice animation.
     */
    public function sendDice(
        int|string $chatId,
        ?string $emoji = null,
        ?array $extraParams = null,
        ?int $messageThreadId = null,
        ?int $directMessagesTopicId = null,
        ?bool $disableNotification = null,
        ?bool $protectContent = null,
        ?bool $allowPaidBroadcast = null,
        ?string $messageEffectId = null,
        ?array $replyParameters = null,
        ?array $keyboard = null,
        ?string $businessConnectionId = null,
        ?array $suggestedPostParameters = null
    ): Future {
        $payload = ['chat_id' => $chatId];
        if ($emoji !== null)
            $payload['emoji'] = $emoji;
        if ($messageThreadId !== null)
            $payload['message_thread_id'] = $messageThreadId;
        if ($directMessagesTopicId !== null)
            $payload['direct_messages_topic_id'] = $directMessagesTopicId;
        if ($disableNotification !== null)
            $payload['disable_notification'] = $disableNotification;
        if ($protectContent !== null)
            $payload['protect_content'] = $protectContent;
        if ($allowPaidBroadcast !== null)
            $payload['allow_paid_broadcast'] = $allowPaidBroadcast;
        if ($messageEffectId !== null)
            $payload['message_effect_id'] = $messageEffectId;
        if ($replyParameters !== null)
            $payload['reply_parameters'] = json_encode($replyParameters);
        if ($keyboard !== null)
            $payload['reply_markup'] = json_encode($keyboard);
        if ($businessConnectionId !== null)
            $payload['business_connection_id'] = $businessConnectionId;
        if ($suggestedPostParameters !== null)
            $payload['suggested_post_parameters'] = json_encode($suggestedPostParameters);
        return $this->request('sendDice', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Send poll (quiz or survey).
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
        ?array $explanationMedia = null,
        ?bool $allowsMultipleAnswers = null,
        ?bool $allowsRevoting = null,
        ?bool $shuffleOptions = null,
        ?bool $allowAddingOptions = null,
        ?bool $hideResultsUntilCloses = null,
        ?bool $membersOnly = null,
        ?array $countryCodes = null,
        ?array $correctOptionIds = null,
        ?int $openPeriod = null,
        ?int $closeDate = null,
        ?bool $isClosed = null,
        ?bool $disableNotification = null,
        ?bool $protectContent = null,
        ?bool $allowPaidBroadcast = null,
        ?string $messageEffectId = null,
        ?array $replyParameters = null,
        ?array $keyboard = null,
        ?string $businessConnectionId = null,
        ?int $messageThreadId = null
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

        if ($isAnonymous !== null)
            $payload['is_anonymous'] = $isAnonymous;
        if ($type !== null)
            $payload['type'] = $type;
        if ($questionEntities !== null)
            $payload['question_entities'] = json_encode($questionEntities);
        if ($media !== null)
            $payload['media'] = json_encode(
                $this->extractPollMediaAttachments($media, $attachments, 'poll_media')
            );
        if ($description !== null)
            $payload['description'] = $description;
        if ($descriptionEntities !== null)
            $payload['description_entities'] = json_encode($descriptionEntities);
        if ($explanation !== null)
            $payload['explanation'] = $explanation;
        if ($explanationEntities !== null)
            $payload['explanation_entities'] = json_encode($explanationEntities);
        if ($explanationMedia !== null)
            $payload['explanation_media'] = json_encode(
                $this->extractPollMediaAttachments($explanationMedia, $attachments, 'explanation_media')
            );
        if ($allowsMultipleAnswers !== null)
            $payload['allows_multiple_answers'] = $allowsMultipleAnswers;
        if ($allowsRevoting !== null)
            $payload['allows_revoting'] = $allowsRevoting;
        if ($shuffleOptions !== null)
            $payload['shuffle_options'] = $shuffleOptions;
        if ($allowAddingOptions !== null)
            $payload['allow_adding_options'] = $allowAddingOptions;
        if ($hideResultsUntilCloses !== null)
            $payload['hide_results_until_closes'] = $hideResultsUntilCloses;
        if ($membersOnly !== null)
            $payload['members_only'] = $membersOnly;
        if ($countryCodes !== null)
            $payload['country_codes'] = json_encode($countryCodes);
        if ($correctOptionIds !== null)
            $payload['correct_option_ids'] = json_encode($correctOptionIds);
        if ($openPeriod !== null)
            $payload['open_period'] = $openPeriod;
        if ($closeDate !== null)
            $payload['close_date'] = $closeDate;
        if ($isClosed !== null)
            $payload['is_closed'] = $isClosed;
        if ($disableNotification !== null)
            $payload['disable_notification'] = $disableNotification;
        if ($protectContent !== null)
            $payload['protect_content'] = $protectContent;
        if ($allowPaidBroadcast !== null)
            $payload['allow_paid_broadcast'] = $allowPaidBroadcast;
        if ($messageEffectId !== null)
            $payload['message_effect_id'] = $messageEffectId;
        if ($replyParameters !== null)
            $payload['reply_parameters'] = json_encode($replyParameters);
        if ($keyboard !== null)
            $payload['reply_markup'] = json_encode($keyboard);
        if ($businessConnectionId !== null)
            $payload['business_connection_id'] = $businessConnectionId;
        if ($messageThreadId !== null)
            $payload['message_thread_id'] = $messageThreadId;

        if ($extraParams !== null)
            $payload += $extraParams;

        return $attachments
            ? $this->requestWithFile('sendPoll', $payload, $attachments)
            : $this->request('sendPoll', $payload);
    }

    /**
     * Stop a running poll.
     */
    public function stopPoll(
        int|string $chatId,
        int $messageId,
        ?array $extraParams = null,
        ?array $keyboard = null,
        ?string $businessConnectionId = null
    ): Future {
        $payload = ['chat_id' => $chatId, 'message_id' => $messageId];
        if ($keyboard !== null)
            $payload['reply_markup'] = json_encode($keyboard);
        if ($businessConnectionId !== null)
            $payload['business_connection_id'] = $businessConnectionId;
        return $this->request('stopPoll', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Send venue location.
     *
     * The foursquare and Google Place identifiers are placed AFTER
     * $extraParams to preserve the original 6-argument signature:
     * sendVenue($chatId, $lat, $lng, $title, $address, ?array $extraParams).
     */
    public function sendVenue(
        int|string $chatId,
        float $latitude,
        float $longitude,
        string $title,
        string $address,
        ?array $extraParams = null,
        ?string $foursquareId = null,
        ?string $foursquareType = null,
        ?string $googlePlaceId = null,
        ?string $googlePlaceType = null
    ): Future {
        $payload = [
            'chat_id' => $chatId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'title' => $title,
            'address' => $address,
        ];
        if ($foursquareId !== null)
            $payload['foursquare_id'] = $foursquareId;
        if ($foursquareType !== null)
            $payload['foursquare_type'] = $foursquareType;
        if ($googlePlaceId !== null)
            $payload['google_place_id'] = $googlePlaceId;
        if ($googlePlaceType !== null)
            $payload['google_place_type'] = $googlePlaceType;
        return $this->request('sendVenue', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Send live location.
     */
    public function sendLocation(
        int|string $chatId,
        float $latitude,
        float $longitude,
        ?array $extraParams = null,
        ?float $horizontalAccuracy = null,
        ?int $livePeriod = null,
        ?int $heading = null,
        ?int $proximityAlertRadius = null
    ): Future {
        $payload = ['chat_id' => $chatId, 'latitude' => $latitude, 'longitude' => $longitude];
        if ($horizontalAccuracy !== null)
            $payload['horizontal_accuracy'] = $horizontalAccuracy;
        if ($livePeriod !== null)
            $payload['live_period'] = $livePeriod;
        if ($heading !== null)
            $payload['heading'] = $heading;
        if ($proximityAlertRadius !== null)
            $payload['proximity_alert_radius'] = $proximityAlertRadius;
        return $this->request('sendLocation', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Send contact info.
     *
     * The $vcard parameter is placed AFTER $extraParams to preserve the
     * original 5-argument signature:
     * sendContact($chatId, $phone, $first, ?string $last, ?array $extraParams).
     */
    public function sendContact(
        int|string $chatId,
        string $phoneNumber,
        string $firstName,
        ?string $lastName = null,
        ?array $extraParams = null,
        ?string $vcard = null
    ): Future {
        $payload = [
            'chat_id' => $chatId,
            'phone_number' => $phoneNumber,
            'first_name' => $firstName,
        ];
        if ($lastName !== null)
            $payload['last_name'] = $lastName;
        if ($vcard !== null)
            $payload['vcard'] = $vcard;
        return $this->request('sendContact', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }
}
