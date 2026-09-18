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
     * Upload PNG sticker file
     */
    public function uploadStickerFile(int $userId, Media $pngSticker): Future
    {
        return $this->requestWithFile('uploadStickerFile', ['user_id' => $userId], ['png_sticker' => $pngSticker->filePath]);
    }

    /**
     * Create new sticker set
     */
    public function createNewStickerSet(int $userId, string $name, string $title, string $emojis, Media $pngSticker, ?array $extraParams = null): Future
    {
        $fields = ['user_id' => $userId, 'name' => $name, 'title' => $title, 'emojis' => $emojis];
        if ($extraParams !== null)
            $fields = array_merge($fields, $extraParams);
        return $this->requestWithFile('createNewStickerSet', $fields, ['png_sticker' => $pngSticker->filePath]);
    }

    /**
     * Add sticker to existing set
     */
    public function addStickerToSet(int $userId, string $name, string $emojis, Media $pngSticker, ?array $extraParams = null): Future
    {
        $fields = ['user_id' => $userId, 'name' => $name, 'emojis' => $emojis];
        if ($extraParams !== null)
            $fields = array_merge($fields, $extraParams);
        return $this->requestWithFile('addStickerToSet', $fields, ['png_sticker' => $pngSticker->filePath]);
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
     * Set thumbnail of a sticker set
     */
    public function setStickerSetThumb(string $name, Media $thumb): Future
    {
        return $this->requestWithFile('setStickerSetThumb', ['name' => $name], ['thumb' => $thumb->filePath]);
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
