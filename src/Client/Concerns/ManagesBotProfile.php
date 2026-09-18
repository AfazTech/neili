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
use Neili\Media;

trait ManagesBotProfile
{
    /**
     * Get bot info (getMe)
     */
    public function getMe(): Future
    {
        return $this->request('getMe', []);
    }

    /**
     * Change the bot's description.
     */
    public function setMyDescription(
        ?string $description = null,
        ?string $languageCode = null,
        ?array $extraParams = null
    ): Future {
        $payload = [];

        if ($description !== null) {
            $payload['description'] = $description;
        }

        if ($languageCode !== null) {
            $payload['language_code'] = $languageCode;
        }

        return $this->request('setMyDescription', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Change the bot's name.
     */
    public function setMyName(
        ?string $name = null,
        ?string $languageCode = null,
        ?array $extraParams = null
    ): Future {
        $payload = [];

        if ($name !== null) {
            $payload['name'] = $name;
        }

        if ($languageCode !== null) {
            $payload['language_code'] = $languageCode;
        }

        return $this->request('setMyName', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Change the bot's short description.
     */
    public function setMyShortDescription(
        ?string $shortDescription = null,
        ?string $languageCode = null,
        ?array $extraParams = null
    ): Future {
        $payload = [];

        if ($shortDescription !== null) {
            $payload['short_description'] = $shortDescription;
        }

        if ($languageCode !== null) {
            $payload['language_code'] = $languageCode;
        }

        return $this->request('setMyShortDescription', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Get the current bot description.
     */
    public function getMyDescription(
        ?string $languageCode = null,
        ?array $extraParams = null
    ): Future {
        $payload = [];

        if ($languageCode !== null) {
            $payload['language_code'] = $languageCode;
        }

        return $this->request('getMyDescription', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Get the current bot name.
     */
    public function getMyName(
        ?string $languageCode = null,
        ?array $extraParams = null
    ): Future {
        $payload = [];

        if ($languageCode !== null) {
            $payload['language_code'] = $languageCode;
        }

        return $this->request('getMyName', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Get the current bot short description.
     */
    public function getMyShortDescription(
        ?string $languageCode = null,
        ?array $extraParams = null
    ): Future {
        $payload = [];

        if ($languageCode !== null) {
            $payload['language_code'] = $languageCode;
        }

        return $this->request('getMyShortDescription', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Change the list of the bot's commands.
     */
    public function setMyCommands(
        array $commands,
        ?array $scope = null,
        ?string $languageCode = null,
        ?array $extraParams = null
    ): Future {
        $payload = [
            'commands' => json_encode($commands)
        ];

        if ($scope !== null) {
            $payload['scope'] = json_encode($scope);
        }

        if ($languageCode !== null) {
            $payload['language_code'] = $languageCode;
        }

        return $this->request('setMyCommands', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Delete the list of the bot's commands.
     */
    public function deleteMyCommands(
        ?array $scope = null,
        ?string $languageCode = null,
        ?array $extraParams = null
    ): Future {
        $payload = [];

        if ($scope !== null) {
            $payload['scope'] = json_encode($scope);
        }

        if ($languageCode !== null) {
            $payload['language_code'] = $languageCode;
        }

        return $this->request('deleteMyCommands', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Get the current list of the bot's commands.
     */
    public function getMyCommands(
        ?array $scope = null,
        ?string $languageCode = null,
        ?array $extraParams = null
    ): Future {
        $payload = [];

        if ($scope !== null) {
            $payload['scope'] = json_encode($scope);
        }

        if ($languageCode !== null) {
            $payload['language_code'] = $languageCode;
        }

        return $this->request('getMyCommands', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Change the default administrator rights requested by the bot.
     */
    public function setMyDefaultAdministratorRights(
        ?array $rights = null,
        ?bool $forChannels = null,
        ?array $extraParams = null
    ): Future {
        $payload = [];

        if ($rights !== null) {
            $payload['rights'] = json_encode($rights);
        }

        if ($forChannels !== null) {
            $payload['for_channels'] = $forChannels;
        }

        return $this->request('setMyDefaultAdministratorRights', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Get the current default administrator rights of the bot.
     */
    public function getMyDefaultAdministratorRights(
        ?bool $forChannels = null,
        ?array $extraParams = null
    ): Future {
        $payload = [];

        if ($forChannels !== null) {
            $payload['for_channels'] = $forChannels;
        }

        return $this->request('getMyDefaultAdministratorRights', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Change the bot's profile photo.
     */
    public function setMyProfilePhoto(Media $photo): Future
    {
        return $this->requestWithFile('setMyProfilePhoto', [], ['photo' => $photo->filePath]);
    }

    /**
     * Remove the profile photo of the bot.
     */
    public function removeMyProfilePhoto(): Future
    {
        return $this->request('removeMyProfilePhoto', []);
    }

    /**
     * Change the emoji status for a given user.
     */
    public function setUserEmojiStatus(
        int $userId,
        ?string $emojiStatusCustomEmojiId = null,
        ?int $emojiStatusExpirationDate = null
    ): Future {
        $payload = ['user_id' => $userId];
        if ($emojiStatusCustomEmojiId !== null) {
            $payload['emoji_status_custom_emoji_id'] = $emojiStatusCustomEmojiId;
        }
        if ($emojiStatusExpirationDate !== null) {
            $payload['emoji_status_expiration_date'] = $emojiStatusExpirationDate;
        }
        return $this->request('setUserEmojiStatus', $payload);
    }

    /**
     * Get the list of profile audios for a user.
     */
    public function getUserProfileAudios(int $userId, ?int $offset = null, ?int $limit = null): Future
    {
        $payload = ['user_id' => $userId];
        if ($offset !== null) {
            $payload['offset'] = $offset;
        }
        if ($limit !== null) {
            $payload['limit'] = $limit;
        }
        return $this->request('getUserProfileAudios', $payload);
    }

    /**
     * Log out from the cloud Bot API server.
     */
    public function logOut(): Future
    {
        return $this->request('logOut', []);
    }

    /**
     * Close the bot instance.
     */
    public function close(): Future
    {
        return $this->request('close', []);
    }
}
