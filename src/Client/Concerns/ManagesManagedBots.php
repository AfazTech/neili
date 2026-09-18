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

trait ManagesManagedBots
{
    /**
     * Get the token of a managed bot.
     */
    public function getManagedBotToken(int $userId): Future
    {
        return $this->request('getManagedBotToken', ['user_id' => $userId]);
    }

    /**
     * Revoke the current token of a managed bot and generate a new one.
     */
    public function replaceManagedBotToken(int $userId): Future
    {
        return $this->request('replaceManagedBotToken', ['user_id' => $userId]);
    }

    /**
     * Get the access settings of a managed bot.
     */
    public function getManagedBotAccessSettings(int $userId): Future
    {
        return $this->request('getManagedBotAccessSettings', ['user_id' => $userId]);
    }

    /**
     * Change the access settings of a managed bot.
     *
     * @param array<int>|null $addedUserIds
     */
    public function setManagedBotAccessSettings(
        int $userId,
        bool $isAccessRestricted,
        ?array $addedUserIds = null
    ): Future {
        $payload = [
            'user_id' => $userId,
            'is_access_restricted' => $isAccessRestricted,
        ];
        if ($addedUserIds !== null) {
            $payload['added_user_ids'] = json_encode($addedUserIds);
        }
        return $this->request('setManagedBotAccessSettings', $payload);
    }
}
