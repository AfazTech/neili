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

trait HandlesRichMessages
{
    /**
     * Walk an InputRichMessage and replace every Media object inside the
     * embedded InputMedia payloads with attach:// references, collecting the
     * underlying files into the $attachments map.
     *
     * The outer InputRichMessageMedia array keeps its structure; only the
     * inner InputMedia objects are rewritten, matching the grammar defined
     * by the Telegram Bot API.
     *
     * @param array $richMessage InputRichMessage payload
     * @param array $attachments File map populated by reference
     * @return array Normalized InputRichMessage payload
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

            // Re-key the collected files with a per-item prefix so that the
            // same field name (e.g. "media") in two InputRichMessageMedia
            // entries does not collide.
            foreach ($scopedFiles as $key => $path) {
                $attachments['rich_' . $index . '_' . $key] = $path;
            }

            // Rewrite the attach:// references inside this item to match the
            // prefixed keys collected above.
            foreach (['media', 'photo', 'thumbnail', 'cover'] as $field) {
                if (
                    isset($normalized[$field])
                    && is_string($normalized[$field])
                    && str_starts_with($normalized[$field], 'attach://')
                ) {
                    $originalKey = substr($normalized[$field], 8);
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
        ?array $extraParams = null
    ): Future {
        $attachments = [];
        $richMessage = $this->extractRichMessageAttachments($richMessage, $attachments);

        $payload = [
            'chat_id' => $chatId,
            'rich_message' => json_encode($richMessage),
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
            $payload['suggested_post_parameters'] = json_encode($suggestedPostParameters);
        }
        if ($replyParameters !== null) {
            $payload['reply_parameters'] = json_encode($replyParameters);
        }
        if ($keyboard !== null) {
            $payload['reply_markup'] = json_encode($keyboard);
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
     *
     * Direct upload of new files and explicit upload of files by a URL isn't
     * supported while streaming, matching the Telegram Bot API rules.
     *
     * @param array $richMessage InputRichMessage payload
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
            'rich_message' => json_encode($richMessage),
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
            $payload['entities'] = json_encode($entities);
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
