<?php

/**
 * @version 2.2.13
 * @author Abolfazl Majidi (Afaz)
 * @package neili
 * @license https://opensource.org/licenses/MIT
 * @link https://github.com/AfazTech/neili
 */

declare(strict_types=1);

namespace Neili\Client\Concerns;

use Amp\Future;
use InvalidArgumentException;
use Neili\Media;

trait SendsMedia
{
    /**
     * Send photo
     * Supports both Media object or URL/file_id string
     */
    public function sendPhoto(int $chatId, string|Media $photo, ?string $caption = null, ?array $keyboard = null, ?array $extraParams = null): Future
    {
        $fields = ['chat_id' => $chatId];
        if ($caption !== null)
            $fields['caption'] = $caption;
        if ($keyboard !== null)
            $fields['reply_markup'] = json_encode($keyboard);
        if ($extraParams !== null)
            $fields = array_merge($fields, $extraParams);

        if ($photo instanceof Media)
            return $this->requestWithFile('sendPhoto', $fields, ['photo' => $photo->filePath]);

        $fields['photo'] = $photo;
        return $this->request('sendPhoto', $fields);
    }

    /**
     * Send live photo (static photo + short video).
     */
    public function sendLivePhoto(
        int $chatId,
        string|Media $livePhoto,
        string|Media $photo,
        ?string $caption = null,
        ?array $keyboard = null,
        ?array $extraParams = null
    ): Future {
        $fields = ['chat_id' => $chatId];
        if ($caption !== null) {
            $fields['caption'] = $caption;
        }
        if ($keyboard !== null) {
            $fields['reply_markup'] = json_encode($keyboard);
        }
        if ($extraParams !== null) {
            $fields = array_merge($fields, $extraParams);
        }

        $files = [];
        if ($livePhoto instanceof Media) {
            $files['live_photo'] = $livePhoto->filePath;
        } else {
            $fields['live_photo'] = $livePhoto;
        }
        if ($photo instanceof Media) {
            $files['photo'] = $photo->filePath;
        } else {
            $fields['photo'] = $photo;
        }

        return $files
            ? $this->requestWithFile('sendLivePhoto', $fields, $files)
            : $this->request('sendLivePhoto', $fields);
    }

    /**
     * Send paid media.
     *
     * Each element of $media may be:
     *   - a Media object (uploaded via multipart/form-data and referenced
     *     with an attach:// key),
     *   - an InputPaidMedia array,
     *   - a string (file_id / URL).
     *
     * @param array $media Array of InputPaidMedia
     */
    public function sendPaidMedia(
        int|string $chatId,
        int $starCount,
        array $media,
        ?string $caption = null,
        ?string $payload = null,
        ?bool $disableNotification = null,
        ?bool $protectContent = null,
        ?array $extraParams = null
    ): Future {
        $fields = [
            'chat_id' => $chatId,
            'star_count' => $starCount,
        ];
        if ($caption !== null) {
            $fields['caption'] = $caption;
        }
        if ($payload !== null) {
            $fields['payload'] = $payload;
        }
        if ($disableNotification !== null) {
            $fields['disable_notification'] = $disableNotification;
        }
        if ($protectContent !== null) {
            $fields['protect_content'] = $protectContent;
        }
        if ($extraParams !== null) {
            $fields = array_merge($fields, $extraParams);
        }

        $attachments = [];
        $normalized = [];

        foreach ($media as $index => $item) {
            if ($item instanceof Media) {
                $attachKey = "file_{$index}_" . bin2hex(random_bytes(4));
                $normalized[] = [
                    'type' => $this->detectPaidMediaType($item->filePath),
                    'media' => "attach://{$attachKey}",
                ];
                $attachments[$attachKey] = $item->filePath;
            } elseif (is_array($item)) {
                $normalized[] = $item;
            } else {
                throw new InvalidArgumentException(
                    "Unsupported paid media item at position {$index}. " .
                    "Expected Media object, InputPaidMedia array, or string."
                );
            }
        }

        if (empty($normalized)) {
            throw new InvalidArgumentException("Paid media items array cannot be empty");
        }

        $fields['media'] = json_encode($normalized);

        return $attachments
            ? $this->requestWithFile('sendPaidMedia', $fields, $attachments)
            : $this->request('sendPaidMedia', $fields);
    }

    /**
     * Send video
     * Supports Media object for file upload or string for URL/file_id
     */
    public function sendVideo(int $chatId, string|Media $video, ?string $caption = null, ?array $keyboard = null, ?array $extraParams = null): Future
    {
        $fields = ['chat_id' => $chatId];
        if ($caption !== null)
            $fields['caption'] = $caption;
        if ($keyboard !== null)
            $fields['reply_markup'] = json_encode($keyboard);
        if ($extraParams !== null)
            $fields = array_merge($fields, $extraParams);

        if ($video instanceof Media)
            return $this->requestWithFile('sendVideo', $fields, ['video' => $video->filePath]);

        $fields['video'] = $video;
        return $this->request('sendVideo', $fields);
    }

    /**
     * Send audio (music or voice)
     */
    public function sendAudio(int $chatId, string|Media $audio, ?string $caption = null, ?array $keyboard = null, ?array $extraParams = null): Future
    {
        $fields = ['chat_id' => $chatId];
        if ($caption !== null)
            $fields['caption'] = $caption;
        if ($keyboard !== null)
            $fields['reply_markup'] = json_encode($keyboard);
        if ($extraParams !== null)
            $fields = array_merge($fields, $extraParams);

        if ($audio instanceof Media)
            return $this->requestWithFile('sendAudio', $fields, ['audio' => $audio->filePath]);

        $fields['audio'] = $audio;
        return $this->request('sendAudio', $fields);
    }

    /**
     * Send document (pdf, zip, etc)
     */
    public function sendDocument(int $chatId, string|Media $document, ?string $caption = null, ?array $keyboard = null, ?array $extraParams = null): Future
    {
        $fields = ['chat_id' => $chatId];
        if ($caption !== null)
            $fields['caption'] = $caption;
        if ($keyboard !== null)
            $fields['reply_markup'] = json_encode($keyboard);
        if ($extraParams !== null)
            $fields = array_merge($fields, $extraParams);

        if ($document instanceof Media)
            return $this->requestWithFile('sendDocument', $fields, ['document' => $document->filePath]);

        $fields['document'] = $document;
        return $this->request('sendDocument', $fields);
    }

    /**
     * Send animation (GIF)
     */
    public function sendAnimation(int $chatId, string|Media $animation, ?string $caption = null, ?array $keyboard = null, ?array $extraParams = null): Future
    {
        $fields = ['chat_id' => $chatId];
        if ($caption !== null)
            $fields['caption'] = $caption;
        if ($keyboard !== null)
            $fields['reply_markup'] = json_encode($keyboard);
        if ($extraParams !== null)
            $fields = array_merge($fields, $extraParams);

        if ($animation instanceof Media)
            return $this->requestWithFile('sendAnimation', $fields, ['animation' => $animation->filePath]);

        $fields['animation'] = $animation;
        return $this->request('sendAnimation', $fields);
    }

    /**
     * Send sticker.
     *
     * Supports uploading new .WEBP, .TGS or .WEBM stickers via a Media object,
     * or passing a file_id / HTTP URL as a string.
     * Video and animated stickers can't be sent via an HTTP URL.
     */
    public function sendSticker(int $chatId, string|Media $sticker, ?array $extraParams = null): Future
    {
        $fields = ['chat_id' => $chatId];
        if ($extraParams !== null) {
            $fields = array_merge($fields, $extraParams);
        }

        if ($sticker instanceof Media) {
            return $this->requestWithFile('sendSticker', $fields, ['sticker' => $sticker->filePath]);
        }

        $fields['sticker'] = $sticker;
        return $this->request('sendSticker', $fields);
    }

    /**
     * Send voice messages.
     */
    public function sendVoice(
        int $chatId,
        string|Media $voice,
        ?string $caption = null,
        ?array $keyboard = null,
        ?array $extraParams = null
    ): Future {
        $fields = ['chat_id' => $chatId];

        if ($caption !== null) {
            $fields['caption'] = $caption;
        }

        if ($keyboard !== null) {
            $fields['reply_markup'] = json_encode($keyboard);
        }

        if ($extraParams !== null) {
            $fields = array_merge($fields, $extraParams);
        }

        if ($voice instanceof Media) {
            return $this->requestWithFile('sendVoice', $fields, ['voice' => $voice->filePath]);
        }

        $fields['voice'] = $voice;
        return $this->request('sendVoice', $fields);
    }

    /**
     * Send video notes (round videos).
     */
    public function sendVideoNote(
        int $chatId,
        string|Media $videoNote,
        ?array $keyboard = null,
        ?array $extraParams = null
    ): Future {
        $fields = ['chat_id' => $chatId];

        if ($keyboard !== null) {
            $fields['reply_markup'] = json_encode($keyboard);
        }

        if ($extraParams !== null) {
            $fields = array_merge($fields, $extraParams);
        }

        if ($videoNote instanceof Media) {
            return $this->requestWithFile('sendVideoNote', $fields, ['video_note' => $videoNote->filePath]);
        }

        $fields['video_note'] = $videoNote;
        return $this->request('sendVideoNote', $fields);
    }

    /**
     * Send a group of photos, videos, documents or audios as an album.
     */
    public function sendMediaGroup(
        int $chatId,
        array $mediaItems,
        ?string $caption = null,
        ?bool $disableNotification = null,
        ?int $replyToMessageId = null,
        ?array $extraParams = null
    ): Future {
        $inputMedia = [];
        $attachments = [];
        $hasLocalFile = false;

        foreach ($mediaItems as $index => $item) {
            if ($item instanceof Media) {
                $attachKey = "file_{$index}_" . bin2hex(random_bytes(4));
                $type = $this->detectMediaType($item->filePath);

                $mediaEntry = [
                    'type' => $type,
                    'media' => "attach://{$attachKey}",
                ];

                if ($index === 0 && $caption !== null) {
                    $mediaEntry['caption'] = $caption;
                }

                $inputMedia[] = $mediaEntry;
                $attachments[$attachKey] = $item->filePath;
                $hasLocalFile = true;
            } elseif (is_string($item)) {
                $type = $this->guessTypeFromString($item);

                $mediaEntry = [
                    'type' => $type,
                    'media' => $item,
                ];

                if ($index === 0 && $caption !== null) {
                    $mediaEntry['caption'] = $caption;
                }

                $inputMedia[] = $mediaEntry;
            } elseif (is_array($item) && isset($item['type'], $item['media'])) {
                $inputMedia[] = $item;
            } else {
                throw new InvalidArgumentException(
                    "Unsupported media item at position {$index}. " .
                    "Expected Media object, string (file_id/url), or InputMedia array."
                );
            }
        }

        if (empty($inputMedia)) {
            throw new InvalidArgumentException("Media items array cannot be empty");
        }

        if (count($inputMedia) > 10) {
            throw new InvalidArgumentException("Telegram allows maximum 10 media items in one group");
        }

        $payload = [
            'chat_id' => $chatId,
            'media' => json_encode($inputMedia),
        ];

        if ($disableNotification !== null) {
            $payload['disable_notification'] = $disableNotification;
        }

        if ($replyToMessageId !== null) {
            $payload['reply_to_message_id'] = $replyToMessageId;
        }

        if ($extraParams) {
            $payload += $extraParams;
        }

        return $hasLocalFile
            ? $this->requestWithFile('sendMediaGroup', $payload, $attachments)
            : $this->request('sendMediaGroup', $payload);
    }

    private function detectMediaType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg', 'png', 'webp', 'heic', 'bmp' => 'photo',
            'gif' => 'animation',
            'mp4', 'mov', 'mkv', 'webm', 'avi' => 'video',
            'mp3', 'm4a', 'ogg', 'wav', 'flac' => 'audio',
            default => 'document',
        };
    }

    /**
     * Detect the InputPaidMedia type from a file extension.
     * Only "photo" and "video" are valid for paid media.
     */
    private function detectPaidMediaType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'mp4', 'mov', 'mkv', 'webm', 'avi' => 'video',
            default => 'photo',
        };
    }

    private function guessTypeFromString(string $value): string
    {
        if (str_starts_with($value, 'http') || str_starts_with($value, 'https')) {
            $ext = strtolower(pathinfo(parse_url($value, PHP_URL_PATH), PATHINFO_EXTENSION));

            return match ($ext) {
                'jpg', 'jpeg', 'png', 'webp' => 'photo',
                'gif' => 'animation',
                'mp4', 'mov', 'webm' => 'video',
                default => 'document',
            };
        }

        if (str_starts_with($value, 'attach://')) {
            return 'document';
        }

        return 'document';
    }
}
