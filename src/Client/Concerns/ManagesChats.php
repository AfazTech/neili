<?php

/**
 * @version 2.2.11
 * @author Abolfazl Majidi (Afaz)
 * @package neili
 * @license https://opensource.org/licenses/MIT
 * @link https://github.com/AfazTech/neili
 */

declare(strict_types=1);

namespace Neili\Client\Concerns;

use Amp\Future;

trait ManagesChats
{
    /**
     * Get chat info
     */
    public function getChat(int|string $chatId): Future
    {
        return $this->request('getChat', ['chat_id' => $chatId]);
    }

    /**
     * Get chat members count
     */
    public function getChatMembersCount(int $chatId): Future
    {
        return $this->request('getChatMembersCount', ['chat_id' => $chatId]);
    }

    /**
     * Get the number of members in a chat.
     */
    public function getChatMemberCount(int $chatId): Future
    {
        return $this->request('getChatMemberCount', ['chat_id' => $chatId]);
    }

    /**
     * Pin message in chat
     */
    public function pinChatMessage(int $chatId, int $messageId, ?bool $disableNotification = false): Future
    {
        return $this->request('pinChatMessage', ['chat_id' => $chatId, 'message_id' => $messageId, 'disable_notification' => $disableNotification]);
    }

    /**
     * Unpin pinned message
     */
    public function unpinChatMessage(int $chatId): Future
    {
        return $this->request('unpinChatMessage', ['chat_id' => $chatId]);
    }

    /**
     * Unpin all pinned messages in a chat.
     */
    public function unpinAllChatMessages(int $chatId): Future
    {
        return $this->request('unpinAllChatMessages', ['chat_id' => $chatId]);
    }

    /**
     * Set chat title
     */
    public function setChatTitle(int $chatId, string $title): Future
    {
        return $this->request('setChatTitle', ['chat_id' => $chatId, 'title' => $title]);
    }

    /**
     * Set chat description
     */
    public function setChatDescription(int $chatId, string $description): Future
    {
        return $this->request('setChatDescription', ['chat_id' => $chatId, 'description' => $description]);
    }

    /**
     * Set chat photo
     */
    public function setChatPhoto(int $chatId, string $photoUrl): Future
    {
        return $this->request('setChatPhoto', ['chat_id' => $chatId, 'photo' => $photoUrl]);
    }

    /**
     * Delete a chat photo.
     */
    public function deleteChatPhoto(int $chatId): Future
    {
        return $this->request('deleteChatPhoto', ['chat_id' => $chatId]);
    }

    /**
     * Leave a chat.
     */
    public function leaveChat(int $chatId): Future
    {
        return $this->request('leaveChat', ['chat_id' => $chatId]);
    }

    /**
     * Set chat-wide permissions
     */
    public function setChatPermissions(int $chatId, array $permissions, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'permissions' => json_encode($permissions)];
        return $this->request('setChatPermissions', $payload + ($extraParams ?? []));
    }

    /**
     * Export chat invite link
     */
    public function exportChatInviteLink(int $chatId): Future
    {
        return $this->request('exportChatInviteLink', ['chat_id' => $chatId]);
    }

    /**
     * Create a new invite link
     */
    public function createChatInviteLink(int $chatId, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId];
        return $this->request('createChatInviteLink', $payload + ($extraParams ?? []));
    }

    /**
     * Edit an existing invite link
     */
    public function editChatInviteLink(int $chatId, string $inviteLink, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'invite_link' => $inviteLink];
        return $this->request('editChatInviteLink', $payload + ($extraParams ?? []));
    }

    /**
     * Revoke an invite link
     */
    public function revokeChatInviteLink(int $chatId, string $inviteLink): Future
    {
        return $this->request('revokeChatInviteLink', ['chat_id' => $chatId, 'invite_link' => $inviteLink]);
    }

    /**
     * Set custom emoji sticker set thumbnail for a chat.
     */
    public function setChatStickerSet(int $chatId, string $stickerSetName): Future
    {
        return $this->request('setChatStickerSet', [
            'chat_id' => $chatId,
            'sticker_set_name' => $stickerSetName
        ]);
    }

    /**
     * Delete custom emoji sticker set from a chat.
     */
    public function deleteChatStickerSet(int $chatId): Future
    {
        return $this->request('deleteChatStickerSet', ['chat_id' => $chatId]);
    }

    /**
     * Change the bot's menu button.
     */
    public function setChatMenuButton(
        ?int $chatId = null,
        ?array $menuButton = null,
        ?array $extraParams = null
    ): Future {
        $payload = [];

        if ($chatId !== null) {
            $payload['chat_id'] = $chatId;
        }

        if ($menuButton !== null) {
            $payload['menu_button'] = json_encode($menuButton);
        }

        return $this->request('setChatMenuButton', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Get the current value of the bot's menu button.
     */
    public function getChatMenuButton(
        ?int $chatId = null,
        ?array $extraParams = null
    ): Future {
        $payload = [];

        if ($chatId !== null) {
            $payload['chat_id'] = $chatId;
        }

        return $this->request('getChatMenuButton', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }
}
