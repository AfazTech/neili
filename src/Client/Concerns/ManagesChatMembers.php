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
    public function getChatAdministrators(int $chatId, ?bool $returnBots = null): Future
    {
        $payload = ['chat_id' => $chatId];
        if ($returnBots !== null) {
            $payload['return_bots'] = $returnBots;
        }
        return $this->request('getChatAdministrators', $payload);
    }

    /**
     * Ban a user in a group, supergroup or channel.
     */
    public function banChatMember(int $chatId, int $userId, ?int $untilDate = null, ?bool $revokeMessages = null, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'user_id' => $userId];
        if ($untilDate !== null) {
            $payload['until_date'] = $untilDate;
        }
        if ($revokeMessages !== null) {
            $payload['revoke_messages'] = $revokeMessages;
        }
        return $this->request('banChatMember', $payload + ($extraParams ?? []));
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
     * Set a tag for a regular member in a group or a supergroup.
     */
    public function setChatMemberTag(
        int|string $chatId,
        int $userId,
        ?string $tag = null
    ): Future {
        $payload = [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ];
        if ($tag !== null) {
            $payload['tag'] = $tag;
        }
        return $this->request('setChatMemberTag', $payload);
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
     * Process a received chat join request query.
     */
    public function answerChatJoinRequestQuery(string $chatJoinRequestQueryId, string $result): Future
    {
        return $this->request('answerChatJoinRequestQuery', [
            'chat_join_request_query_id' => $chatJoinRequestQueryId,
            'result' => $result,
        ]);
    }

    /**
     * Process a received chat join request query by showing a Mini App.
     */
    public function sendChatJoinRequestWebApp(string $chatJoinRequestQueryId, string $webAppUrl): Future
    {
        return $this->request('sendChatJoinRequestWebApp', [
            'chat_join_request_query_id' => $chatJoinRequestQueryId,
            'web_app_url' => $webAppUrl,
        ]);
    }

    /**
     * Get the list of boosts added to a chat by a user.
     */
    public function getUserChatBoosts(int|string $chatId, int $userId): Future
    {
        return $this->request('getUserChatBoosts', [
            'chat_id' => $chatId,
            'user_id' => $userId,
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
