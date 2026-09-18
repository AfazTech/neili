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
     * Set game score
     */
    public function setGameScore(int $userId, int $score, int $chatId, int $messageId, ?bool $force = false, ?bool $disableEditMessage = false): Future
    {
        $payload = ['user_id' => $userId, 'score' => $score, 'chat_id' => $chatId, 'message_id' => $messageId];
        if ($force !== null)
            $payload['force'] = $force;
        if ($disableEditMessage !== null)
            $payload['disable_edit_message'] = $disableEditMessage;
        return $this->request('setGameScore', $payload);
    }

    /**
     * Get game high scores
     */
    public function getGameHighScores(int $userId, int $chatId, int $messageId): Future
    {
        return $this->request('getGameHighScores', ['user_id' => $userId, 'chat_id' => $chatId, 'message_id' => $messageId]);
    }
}
