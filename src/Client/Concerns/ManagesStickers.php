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
use Neili\Media;

trait ManagesStickers
{
    /**
     * Get sticker set info
     */
    public function getStickerSet(string $name): Future
    {
        return $this->request('getStickerSet', ['name' => $name]);
    }

    /**
     * Upload a sticker file for later use in createNewStickerSet,
     * addStickerToSet, or replaceStickerInSet.
     *
     * @param Media  $sticker       The sticker file (.WEBP, .PNG, .TGS or .WEBM)
     * @param string $stickerFormat One of "static", "animated", "video"
     */
    public function uploadStickerFile(int $userId, Media $sticker, string $stickerFormat = 'static'): Future
    {
        return $this->requestWithFile(
            'uploadStickerFile',
            [
                'user_id' => $userId,
                'sticker_format' => $stickerFormat,
            ],
            ['sticker' => $sticker->filePath]
        );
    }

    /**
     * Create a new sticker set owned by a user.
     *
     * @param array<int, array> $stickers List of 1-50 InputSticker payloads
     */
    public function createNewStickerSet(
        int $userId,
        string $name,
        string $title,
        array $stickers,
        ?string $stickerType = null,
        ?bool $needsRepainting = null
    ): Future {
        $payload = [
            'user_id' => $userId,
            'name' => $name,
            'title' => $title,
            'stickers' => json_encode($stickers),
        ];

        if ($stickerType !== null) {
            $payload['sticker_type'] = $stickerType;
        }
        if ($needsRepainting !== null) {
            $payload['needs_repainting'] = $needsRepainting;
        }

        return $this->request('createNewStickerSet', $payload);
    }

    /**
     * Add a new sticker to a set created by the bot.
     *
     * @param array $sticker A single InputSticker payload
     */
    public function addStickerToSet(int $userId, string $name, array $sticker): Future
    {
        return $this->request('addStickerToSet', [
            'user_id' => $userId,
            'name' => $name,
            'sticker' => json_encode($sticker),
        ]);
    }

    /**
     * Delete sticker from set
     */
    public function deleteStickerFromSet(string $stickerId): Future
    {
        return $this->request('deleteStickerFromSet', ['sticker' => $stickerId]);
    }

    /**
     * Set sticker position inside set
     */
    public function setStickerPositionInSet(string $stickerId, int $position): Future
    {
        return $this->request('setStickerPositionInSet', ['sticker' => $stickerId, 'position' => $position]);
    }

    /**
     * Replace an existing sticker in a sticker set with a new one.
     *
     * @param array $sticker InputSticker payload
     */
    public function replaceStickerInSet(
        int $userId,
        string $name,
        string $oldSticker,
        array $sticker
    ): Future {
        return $this->request('replaceStickerInSet', [
            'user_id' => $userId,
            'name' => $name,
            'old_sticker' => $oldSticker,
            'sticker' => json_encode($sticker),
        ]);
    }

    /**
     * Set the thumbnail of a regular or mask sticker set.
     */
    public function setStickerSetThumbnail(
        string $name,
        int $userId,
        ?Media $thumbnail = null,
        ?string $format = null,
        ?array $extraParams = null
    ): Future {
        $fields = [
            'name' => $name,
            'user_id' => $userId
        ];

        if ($format !== null) {
            $fields['format'] = $format;
        }

        if ($extraParams !== null) {
            $fields = array_merge($fields, $extraParams);
        }

        if ($thumbnail instanceof Media) {
            return $this->requestWithFile('setStickerSetThumbnail', $fields, ['thumbnail' => $thumbnail->filePath]);
        }

        return $this->request('setStickerSetThumbnail', $fields);
    }

    /**
     * Set the title of a created sticker set.
     */
    public function setStickerSetTitle(string $name, string $title): Future
    {
        return $this->request('setStickerSetTitle', [
            'name' => $name,
            'title' => $title
        ]);
    }

    /**
     * Delete a sticker set.
     */
    public function deleteStickerSet(string $name): Future
    {
        return $this->request('deleteStickerSet', ['name' => $name]);
    }

    /**
     * Set the thumbnail of a custom emoji sticker set.
     */
    public function setCustomEmojiStickerSetThumbnail(
        string $name,
        ?string $customEmojiId = null,
        ?array $extraParams = null
    ): Future {
        $payload = ['name' => $name];

        if ($customEmojiId !== null) {
            $payload['custom_emoji_id'] = $customEmojiId;
        }

        return $this->request('setCustomEmojiStickerSetThumbnail', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Set the emoji list of a sticker.
     */
    public function setStickerEmojiList(string $stickerId, array $emojiList): Future
    {
        return $this->request('setStickerEmojiList', [
            'sticker' => $stickerId,
            'emoji_list' => json_encode($emojiList)
        ]);
    }

    /**
     * Set the keywords of a sticker.
     */
    public function setStickerKeywords(string $stickerId, array $keywords): Future
    {
        return $this->request('setStickerKeywords', [
            'sticker' => $stickerId,
            'keywords' => json_encode($keywords)
        ]);
    }

    /**
     * Set the mask position of a mask sticker.
     */
    public function setStickerMaskPosition(string $stickerId, array $maskPosition): Future
    {
        return $this->request('setStickerMaskPosition', [
            'sticker' => $stickerId,
            'mask_position' => json_encode($maskPosition)
        ]);
    }

    /**
     * Get custom emoji stickers.
     */
    public function getCustomEmojiStickers(array $customEmojiIds): Future
    {
        return $this->request('getCustomEmojiStickers', [
            'custom_emoji_ids' => json_encode($customEmojiIds)
        ]);
    }
}
