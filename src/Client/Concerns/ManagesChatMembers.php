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

trait ManagesChatMembers
{
    /**
     * Get specific chat member info
     */
    public function getChatMember(int $chatId, int $userId): Future
    {
        return $this->request('getChatMember', ['chat_id' => $chatId, 'user_id' => $userId]);
    }

    /**
     * Get chat administrators
     */
    public function getChatAdministrators(int $chatId): Future
    {
        return $this->request('getChatAdministrators', ['chat_id' => $chatId]);
    }

    /**
     * Kick user from chat
     */
    public function kickChatMember(int $chatId, int $userId, ?int $untilDate = null, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'user_id' => $userId];
        if ($untilDate !== null)
            $payload['until_date'] = $untilDate;
        return $this->request('kickChatMember', $payload + ($extraParams ?? []));
    }

    /**
     * Unban user
     */
    public function unbanChatMember(int $chatId, int $userId, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'user_id' => $userId];
        return $this->request('unbanChatMember', $payload + ($extraParams ?? []));
    }

    /**
     * Restrict user permissions in chat
     */
    public function restrictChatMember(int $chatId, int $userId, array $permissions, ?int $untilDate = null, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'user_id' => $userId, 'permissions' => json_encode($permissions)];
        if ($untilDate !== null)
            $payload['until_date'] = $untilDate;
        return $this->request('restrictChatMember', $payload + ($extraParams ?? []));
    }

    /**
     * Promote user with admin privileges
     */
    public function promoteChatMember(int $chatId, int $userId, array $privileges, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'user_id' => $userId] + $privileges;
        return $this->request('promoteChatMember', $payload + ($extraParams ?? []));
    }

    /**
     * Set a custom title for an administrator in a supergroup.
     */
    public function setChatAdministratorCustomTitle(
        int $chatId,
        int $userId,
        string $customTitle
    ): Future {
        return $this->request('setChatAdministratorCustomTitle', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'custom_title' => $customTitle
        ]);
    }

    /**
     * Ban a channel chat in a supergroup or a channel.
     */
    public function banChatSenderChat(
        int $chatId,
        int $senderChatId,
        ?array $extraParams = null
    ): Future {
        $payload = [
            'chat_id' => $chatId,
            'sender_chat_id' => $senderChatId
        ];

        return $this->request('banChatSenderChat', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Unban a previously banned channel chat in a supergroup or a channel.
     */
    public function unbanChatSenderChat(
        int $chatId,
        int $senderChatId,
        ?array $extraParams = null
    ): Future {
        $payload = [
            'chat_id' => $chatId,
            'sender_chat_id' => $senderChatId
        ];

        return $this->request('unbanChatSenderChat', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Get the list of banned users in a supergroup or channel.
     */
    public function getChatBannedUsers(int $chatId): Future
    {
        return $this->request('getChatBannedUsers', ['chat_id' => $chatId]);
    }

    /**
     * Approve a chat join request.
     */
    public function approveChatJoinRequest(int $chatId, int $userId): Future
    {
        return $this->request('approveChatJoinRequest', [
            'chat_id' => $chatId,
            'user_id' => $userId
        ]);
    }

    /**
     * Decline a chat join request.
     */
    public function declineChatJoinRequest(int $chatId, int $userId): Future
    {
        return $this->request('declineChatJoinRequest', [
            'chat_id' => $chatId,
            'user_id' => $userId
        ]);
    }

    /**
     * Get user profile photos
     */
    public function getUserProfilePhotos(int $userId, ?int $offset = null, ?int $limit = null): Future
    {
        $payload = ['user_id' => $userId];
        if ($offset !== null)
            $payload['offset'] = $offset;
        if ($limit !== null)
            $payload['limit'] = $limit;
        return $this->request('getUserProfilePhotos', $payload);
    }
}
