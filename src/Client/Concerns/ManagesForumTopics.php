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

trait ManagesForumTopics
{
    /**
     * Create a topic in a forum supergroup chat or a private chat with a user.
     */
    public function createForumTopic(
        int|string $chatId,
        string $name,
        ?int $iconColor = null,
        ?string $iconCustomEmojiId = null,
        ?array $extraParams = null
    ): Future {
        $payload = [
            'chat_id' => $chatId,
            'name' => $name,
        ];

        if ($iconColor !== null) {
            $payload['icon_color'] = $iconColor;
        }

        if ($iconCustomEmojiId !== null) {
            $payload['icon_custom_emoji_id'] = $iconCustomEmojiId;
        }

        return $this->request('createForumTopic', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Edit name and icon of a topic in a forum supergroup chat.
     */
    public function editForumTopic(
        int|string $chatId,
        int $messageThreadId,
        ?string $name = null,
        ?string $iconCustomEmojiId = null,
        ?array $extraParams = null
    ): Future {
        $payload = [
            'chat_id' => $chatId,
            'message_thread_id' => $messageThreadId,
        ];

        if ($name !== null) {
            $payload['name'] = $name;
        }

        if ($iconCustomEmojiId !== null) {
            $payload['icon_custom_emoji_id'] = $iconCustomEmojiId;
        }

        return $this->request('editForumTopic', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Close an open topic in a forum supergroup chat.
     */
    public function closeForumTopic(int|string $chatId, int $messageThreadId): Future
    {
        return $this->request('closeForumTopic', [
            'chat_id' => $chatId,
            'message_thread_id' => $messageThreadId,
        ]);
    }

    /**
     * Reopen a closed topic in a forum supergroup chat.
     */
    public function reopenForumTopic(int|string $chatId, int $messageThreadId): Future
    {
        return $this->request('reopenForumTopic', [
            'chat_id' => $chatId,
            'message_thread_id' => $messageThreadId,
        ]);
    }

    /**
     * Delete a forum topic along with all its messages.
     */
    public function deleteForumTopic(int|string $chatId, int $messageThreadId): Future
    {
        return $this->request('deleteForumTopic', [
            'chat_id' => $chatId,
            'message_thread_id' => $messageThreadId,
        ]);
    }

    /**
     * Clear the list of pinned messages in a forum topic.
     */
    public function unpinAllForumTopicMessages(int|string $chatId, int $messageThreadId): Future
    {
        return $this->request('unpinAllForumTopicMessages', [
            'chat_id' => $chatId,
            'message_thread_id' => $messageThreadId,
        ]);
    }

    /**
     * Clear the list of pinned messages in a General forum topic.
     */
    public function unpinAllGeneralForumTopicMessages(int|string $chatId): Future
    {
        return $this->request('unpinAllGeneralForumTopicMessages', ['chat_id' => $chatId]);
    }

    /**
     * Get custom emoji stickers, which can be used as a forum topic icon by any user.
     */
    public function getForumTopicIconStickers(): Future
    {
        return $this->request('getForumTopicIconStickers', []);
    }

    /**
     * Edit the name of the 'General' topic in a forum supergroup chat.
     */
    public function editGeneralForumTopic(int|string $chatId, string $name): Future
    {
        return $this->request('editGeneralForumTopic', [
            'chat_id' => $chatId,
            'name' => $name,
        ]);
    }

    /**
     * Close an open 'General' topic in a forum supergroup chat.
     */
    public function closeGeneralForumTopic(int|string $chatId): Future
    {
        return $this->request('closeGeneralForumTopic', ['chat_id' => $chatId]);
    }

    /**
     * Reopen a closed 'General' topic in a forum supergroup chat.
     */
    public function reopenGeneralForumTopic(int|string $chatId): Future
    {
        return $this->request('reopenGeneralForumTopic', ['chat_id' => $chatId]);
    }

    /**
     * Hide the 'General' topic in a forum supergroup chat.
     */
    public function hideGeneralForumTopic(int|string $chatId): Future
    {
        return $this->request('hideGeneralForumTopic', ['chat_id' => $chatId]);
    }

    /**
     * Unhide the 'General' topic in a forum supergroup chat.
     */
    public function unhideGeneralForumTopic(int|string $chatId): Future
    {
        return $this->request('unhideGeneralForumTopic', ['chat_id' => $chatId]);
    }
}
