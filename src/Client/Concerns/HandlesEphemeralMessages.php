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

trait HandlesEphemeralMessages
{
    /**
     * Edit an ephemeral text or rich message.
     *
     * @param array|null $richMessage InputRichMessage payload
     */
    public function editEphemeralMessageText(
        int|string $chatId,
        int $receiverUserId,
        int $ephemeralMessageId,
        ?string $text = null,
        ?array $richMessage = null,
        ?string $parseMode = null,
        ?array $entities = null,
        ?array $linkPreviewOptions = null,
        ?array $keyboard = null,
        ?array $extraParams = null
    ): Future {
        $payload = [
            'chat_id' => $chatId,
            'receiver_user_id' => $receiverUserId,
            'ephemeral_message_id' => $ephemeralMessageId,
        ];
        if ($text !== null) {
            $payload['text'] = $text;
        }
        if ($richMessage !== null) {
            $payload['rich_message'] = json_encode($richMessage);
        }
        if ($parseMode !== null) {
            $payload['parse_mode'] = $parseMode;
        }
        if ($entities !== null) {
            $payload['entities'] = json_encode($entities);
        }
        if ($linkPreviewOptions !== null) {
            $payload['link_preview_options'] = json_encode($linkPreviewOptions);
        }
        if ($keyboard !== null) {
            $payload['reply_markup'] = json_encode($keyboard);
        }
        return $this->request('editEphemeralMessageText', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Edit the media of an ephemeral message.
     *
     * @param array $media InputMedia payload
     */
    public function editEphemeralMessageMedia(
        int|string $chatId,
        int $receiverUserId,
        int $ephemeralMessageId,
        array $media,
        ?array $keyboard = null,
        ?array $extraParams = null
    ): Future {
        $payload = [
            'chat_id' => $chatId,
            'receiver_user_id' => $receiverUserId,
            'ephemeral_message_id' => $ephemeralMessageId,
            'media' => json_encode($media),
        ];
        if ($keyboard !== null) {
            $payload['reply_markup'] = json_encode($keyboard);
        }
        return $this->request('editEphemeralMessageMedia', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Edit the caption of an ephemeral message.
     */
    public function editEphemeralMessageCaption(
        int|string $chatId,
        int $receiverUserId,
        int $ephemeralMessageId,
        ?string $caption = null,
        ?string $parseMode = null,
        ?array $captionEntities = null,
        ?bool $showCaptionAboveMedia = null,
        ?array $keyboard = null,
        ?array $extraParams = null
    ): Future {
        $payload = [
            'chat_id' => $chatId,
            'receiver_user_id' => $receiverUserId,
            'ephemeral_message_id' => $ephemeralMessageId,
        ];
        if ($caption !== null) {
            $payload['caption'] = $caption;
        }
        if ($parseMode !== null) {
            $payload['parse_mode'] = $parseMode;
        }
        if ($captionEntities !== null) {
            $payload['caption_entities'] = json_encode($captionEntities);
        }
        if ($showCaptionAboveMedia !== null) {
            $payload['show_caption_above_media'] = $showCaptionAboveMedia;
        }
        if ($keyboard !== null) {
            $payload['reply_markup'] = json_encode($keyboard);
        }
        return $this->request('editEphemeralMessageCaption', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Edit only the reply markup of an ephemeral message.
     */
    public function editEphemeralMessageReplyMarkup(
        int|string $chatId,
        int $receiverUserId,
        int $ephemeralMessageId,
        ?array $keyboard = null,
        ?array $extraParams = null
    ): Future {
        $payload = [
            'chat_id' => $chatId,
            'receiver_user_id' => $receiverUserId,
            'ephemeral_message_id' => $ephemeralMessageId,
        ];
        if ($keyboard !== null) {
            $payload['reply_markup'] = json_encode($keyboard);
        }
        return $this->request('editEphemeralMessageReplyMarkup', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Delete an ephemeral message.
     */
    public function deleteEphemeralMessage(
        int|string $chatId,
        int $receiverUserId,
        int $ephemeralMessageId
    ): Future {
        return $this->request('deleteEphemeralMessage', [
            'chat_id' => $chatId,
            'receiver_user_id' => $receiverUserId,
            'ephemeral_message_id' => $ephemeralMessageId,
        ]);
    }
}
