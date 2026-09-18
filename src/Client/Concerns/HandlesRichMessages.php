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

trait HandlesRichMessages
{
    /**
     * Send a rich message.
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
        return $this->request('sendRichMessage', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Stream a partial rich message to a user.
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
