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
use InvalidArgumentException;

trait HandlesGames
{
    /**
     * Send game message
     */
    public function sendGame(int $chatId, string $gameShortName, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'game_short_name' => $gameShortName];
        return $this->request('sendGame', $payload + ($extraParams ?? []));
    }

    /**
     * Set game score.
     *
     * Either provide both $chatId and $messageId, or $inlineMessageId.
     */
    public function setGameScore(
        int $userId,
        int $score,
        ?int $chatId = null,
        ?int $messageId = null,
        ?bool $force = null,
        ?bool $disableEditMessage = null,
        ?string $inlineMessageId = null
    ): Future {
        $payload = ['user_id' => $userId, 'score' => $score];

        if ($inlineMessageId !== null) {
            $payload['inline_message_id'] = $inlineMessageId;
        } else {
            if ($chatId === null || $messageId === null) {
                throw new InvalidArgumentException(
                    'Either inline_message_id, or both chat_id and message_id must be provided.'
                );
            }
            $payload['chat_id'] = $chatId;
            $payload['message_id'] = $messageId;
        }

        if ($force !== null) {
            $payload['force'] = $force;
        }
        if ($disableEditMessage !== null) {
            $payload['disable_edit_message'] = $disableEditMessage;
        }

        return $this->request('setGameScore', $payload);
    }

    /**
     * Get game high scores.
     *
     * Either provide both $chatId and $messageId, or $inlineMessageId.
     */
    public function getGameHighScores(
        int $userId,
        ?int $chatId = null,
        ?int $messageId = null,
        ?string $inlineMessageId = null
    ): Future {
        $payload = ['user_id' => $userId];

        if ($inlineMessageId !== null) {
            $payload['inline_message_id'] = $inlineMessageId;
        } else {
            if ($chatId === null || $messageId === null) {
                throw new InvalidArgumentException(
                    'Either inline_message_id, or both chat_id and message_id must be provided.'
                );
            }
            $payload['chat_id'] = $chatId;
            $payload['message_id'] = $messageId;
        }

        return $this->request('getGameHighScores', $payload);
    }
}
