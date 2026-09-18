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

trait HandlesVerification
{
    /**
     * Verify a user on behalf of the organization which is represented by the bot.
     */
    public function verifyUser(int $userId, ?string $customDescription = null): Future
    {
        $payload = ['user_id' => $userId];
        if ($customDescription !== null) {
            $payload['custom_description'] = $customDescription;
        }
        return $this->request('verifyUser', $payload);
    }

    /**
     * Verify a chat on behalf of the organization which is represented by the bot.
     */
    public function verifyChat(int|string $chatId, ?string $customDescription = null): Future
    {
        $payload = ['chat_id' => $chatId];
        if ($customDescription !== null) {
            $payload['custom_description'] = $customDescription;
        }
        return $this->request('verifyChat', $payload);
    }

    /**
     * Remove verification from a user.
     */
    public function removeUserVerification(int $userId): Future
    {
        return $this->request('removeUserVerification', ['user_id' => $userId]);
    }

    /**
     * Remove verification from a chat.
     */
    public function removeChatVerification(int|string $chatId): Future
    {
        return $this->request('removeChatVerification', ['chat_id' => $chatId]);
    }
}
