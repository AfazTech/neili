<?php

/**
 * @version 2.2.12
 * @author Abolfazl Majidi (Afaz)
 * @package neili
 * @license https://opensource.org/licenses/MIT
 * @link https://github.com/AfazTech/neili
 */

declare(strict_types=1);

namespace Neili\Client\Concerns;

use Amp\Future;

trait HandlesSuggestedPosts
{
    /**
     * Approve a suggested post in a direct messages chat.
     */
    public function approveSuggestedPost(
        int $chatId,
        int $messageId,
        ?int $sendDate = null
    ): Future {
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ];
        if ($sendDate !== null) {
            $payload['send_date'] = $sendDate;
        }
        return $this->request('approveSuggestedPost', $payload);
    }

    /**
     * Decline a suggested post in a direct messages chat.
     */
    public function declineSuggestedPost(
        int $chatId,
        int $messageId,
        ?string $comment = null
    ): Future {
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ];
        if ($comment !== null) {
            $payload['comment'] = $comment;
        }
        return $this->request('declineSuggestedPost', $payload);
    }
}
