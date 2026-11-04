<?php

declare(strict_types=1);

namespace Neili\Client\Concerns;

use Amp\Future;

trait HandlesRichMessages
{
    /**
     * Walk an InputRichMessage and replace every Media object inside the
     * embedded InputMedia payloads with attach:// references, collecting the
     * underlying files into the $attachments map.
     */
    private function extractRichMessageAttachments(array $richMessage, array &$attachments): array
    {
        if (!isset($richMessage['media']) || !is_array($richMessage['media'])) {
            return $richMessage;
        }

        foreach ($richMessage['media'] as $index => $item) {
            if (!is_array($item) || !isset($item['media']) || !is_array($item['media'])) {
                continue;
            }

            $scopedFiles = [];
            $normalized = $this->extractMediaAttachments($item['media'], $scopedFiles);

            foreach ($scopedFiles as $key => $path) {
                $attachments['rich_' . $index . '_' . $key] = $path;
            }

            foreach (['media', 'photo', 'thumbnail', 'cover'] as $field) {
                if (
                    isset($normalized[$field])
                    && is_string($normalized[$field])
                    && str_starts_with($normalized[$field], 'attach://')
                ) {
                    $originalKey = substr($normalized[$field], strlen('attach://'));
                    $normalized[$field] = 'attach://rich_' . $index . '_' . $originalKey;
                }
            }

            $richMessage['media'][$index]['media'] = $normalized;
        }

        return $richMessage;
    }

    /**
     * Send a rich message.
     *
     * The rich_message payload may embed local Media objects inside its
     * InputRichMessageMedia entries; those are uploaded via multipart and
     * referenced with attach:// links.
     *
     * @param array $richMessage InputRichMessage payload
     */
    public function sendRichMessage(
        int|string $chatId,
        array $richMessage,
        ?bool $disableNotification = null,
        ?bool $protectContent = null,
        ?bool $allowPaidBroadcast = null,
        ?string $messageEffectId = null,
        ?array $suggestedPostParameters = null,
        ?array $replyParameters = null,
        ?array $keyboard = null,
        ?array $extraParams = null,
        ?int $messageThreadId = null,
        ?int $directMessagesTopicId = null,
        ?string $businessConnectionId = null,
        ?array $ephemeralMessageParameters = null
    ): Future {
        $attachments = [];
        $richMessage = $this->extractRichMessageAttachments($richMessage, $attachments);

        $payload = [
            'chat_id' => $chatId,
            'rich_message' => $this->encodeJson($richMessage),
        ];
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
        if ($suggestedPostParameters !== null) {
            $payload['suggested_post_parameters'] = $this->encodeJson($suggestedPostParameters);
        }
        if ($replyParameters !== null) {
            $payload['reply_parameters'] = $this->encodeJson($replyParameters);
        }
        if ($keyboard !== null) {
            $payload['reply_markup'] = $this->encodeJson($keyboard);
        }
        if ($messageThreadId !== null) {
            $payload['message_thread_id'] = $messageThreadId;
        }
        if ($directMessagesTopicId !== null) {
            $payload['direct_messages_topic_id'] = $directMessagesTopicId;
        }
        if ($businessConnectionId !== null) {
            $payload['business_connection_id'] = $businessConnectionId;
        }
        if ($ephemeralMessageParameters !== null) {
            $payload['ephemeral_message_parameters'] = $this->encodeJson($ephemeralMessageParameters);
        }
        if ($extraParams !== null) {
            $payload = array_merge($payload, $extraParams);
        }

        return $attachments
            ? $this->requestWithFile('sendRichMessage', $payload, $attachments)
            : $this->request('sendRichMessage', $payload);
    }

    /**
     * Stream a partial rich message to a user.
     */
    public function sendRichMessageDraft(
        int $chatId,
        int $draftId,
        array $richMessage,
        ?int $messageThreadId = null,
        ?bool $canStop = null,
        ?bool $keepOnStop = null
    ): Future {
        $payload = [
            'chat_id' => $chatId,
            'draft_id' => $draftId,
            'rich_message' => $this->encodeJson($richMessage),
        ];
        if ($messageThreadId !== null) {
            $payload['message_thread_id'] = $messageThreadId;
        }
        if ($canStop !== null) {
            $payload['can_stop'] = $canStop;
        }
        if ($keepOnStop !== null) {
            $payload['keep_on_stop'] = $keepOnStop;
        }
        return $this->request('sendRichMessageDraft', $payload);
    }

    /**
     * Stream a partial text message to a user.
     */
    public function sendMessageDraft(
        int $chatId,
        int $draftId,
        ?string $text = null,
        ?string $parseMode = null,
        ?array $entities = null,
        ?int $messageThreadId = null,
        ?bool $canStop = null,
        ?bool $keepOnStop = null
    ): Future {
        $payload = [
            'chat_id' => $chatId,
            'draft_id' => $draftId,
        ];
        if ($text !== null) {
            $payload['text'] = $text;
        }
        if ($parseMode !== null) {
            $payload['parse_mode'] = $parseMode;
        }
        if ($entities !== null) {
            $payload['entities'] = $this->encodeJson($entities);
        }
        if ($messageThreadId !== null) {
            $payload['message_thread_id'] = $messageThreadId;
        }
        if ($canStop !== null) {
            $payload['can_stop'] = $canStop;
        }
        if ($keepOnStop !== null) {
            $payload['keep_on_stop'] = $keepOnStop;
        }
        return $this->request('sendMessageDraft', $payload);
    }
}
