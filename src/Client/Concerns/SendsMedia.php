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
use Neili\Media;

trait SendsMedia
{
    /**
     * Merge caption-related fields into a payload.
     *
     * Used by sendPhoto, sendVideo, sendAnimation, sendLivePhoto and
     * sendPaidMedia, which all share the same caption grammar.
     */
    private function applyMediaCaption(
        array $fields,
        ?string $caption,
        ?string $parseMode,
        ?array $captionEntities,
        ?bool $showCaptionAboveMedia
    ): array {
        if ($caption !== null) {
            $fields['caption'] = $caption;
        }
        if ($parseMode !== null) {
            $fields['parse_mode'] = $parseMode;
        }
        if ($captionEntities !== null) {
            $fields['caption_entities'] = json_encode($captionEntities);
        }
        if ($showCaptionAboveMedia !== null) {
            $fields['show_caption_above_media'] = $showCaptionAboveMedia;
        }
        return $fields;
    }

    /**
     * Send photo.
     * Supports both Media object or URL/file_id string.
     */
    public function sendPhoto(
        int|string $chatId,
        string|Media $photo,
        ?string $caption = null,
        ?array $keyboard = null,
        ?array $extraParams = null,
        ?string $parseMode = null,
        ?array $captionEntities = null,
        ?bool $showCaptionAboveMedia = null,
        ?bool $hasSpoiler = null
    ): Future {
        $fields = ['chat_id' => $chatId];
        $fields = $this->applyMediaCaption($fields, $caption, $parseMode, $captionEntities, $showCaptionAboveMedia);

        if ($hasSpoiler !== null)
            $fields['has_spoiler'] = $hasSpoiler;
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
        int|string $chatId,
        string|Media $livePhoto,
        string|Media $photo,
        ?string $caption = null,
        ?array $keyboard = null,
        ?array $extraParams = null,
        ?string $parseMode = null,
        ?array $captionEntities = null,
        ?bool $showCaptionAboveMedia = null,
        ?bool $hasSpoiler = null
    ): Future {
        $fields = ['chat_id' => $chatId];
        $fields = $this->applyMediaCaption($fields, $caption, $parseMode, $captionEntities, $showCaptionAboveMedia);

        if ($hasSpoiler !== null)
            $fields['has_spoiler'] = $hasSpoiler;
        if ($keyboard !== null)
            $fields['reply_markup'] = json_encode($keyboard);
        if ($extraParams !== null)
            $fields = array_merge($fields, $extraParams);

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
     * @param array $media Array of InputPaidMedia
     * @param array|null $replyParameters          ReplyParameters payload
     * @param array|null $suggestedPostParameters  SuggestedPostParameters payload
     */
    public function sendPaidMedia(
        int|string $chatId,
        int $starCount,
        array $media,
        ?string $caption = null,
        ?string $payload = null,
        ?bool $disableNotification = null,
        ?bool $protectContent = null,
        ?array $extraParams = null,
        ?string $parseMode = null,
        ?array $captionEntities = null,
        ?bool $showCaptionAboveMedia = null,
        ?array $replyParameters = null,
        ?array $suggestedPostParameters = null,
        ?string $businessConnectionId = null,
        ?int $messageThreadId = null,
        ?int $directMessagesTopicId = null
    ): Future {
        $fields = [
            'chat_id' => $chatId,
            'star_count' => $starCount,
        ];
        $fields = $this->applyMediaCaption($fields, $caption, $parseMode, $captionEntities, $showCaptionAboveMedia);

        if ($payload !== null)
            $fields['payload'] = $payload;
        if ($disableNotification !== null)
            $fields['disable_notification'] = $disableNotification;
        if ($protectContent !== null)
            $fields['protect_content'] = $protectContent;
        if ($replyParameters !== null)
            $fields['reply_parameters'] = json_encode($replyParameters);
        if ($suggestedPostParameters !== null)
            $fields['suggested_post_parameters'] = json_encode($suggestedPostParameters);
        if ($businessConnectionId !== null)
            $fields['business_connection_id'] = $businessConnectionId;
        if ($messageThreadId !== null)
            $fields['message_thread_id'] = $messageThreadId;
        if ($directMessagesTopicId !== null)
            $fields['direct_messages_topic_id'] = $directMessagesTopicId;
        if ($extraParams !== null)
            $fields = array_merge($fields, $extraParams);

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
     * Send video.
     */
    public function sendVideo(
        int|string $chatId,
        string|Media $video,
        ?string $caption = null,
        ?array $keyboard = null,
        ?array $extraParams = null,
        ?string $parseMode = null,
        ?array $captionEntities = null,
        ?bool $showCaptionAboveMedia = null,
        ?bool $hasSpoiler = null,
        ?int $duration = null,
        ?int $width = null,
        ?int $height = null,
        Media|string|null $thumbnail = null,
        Media|string|null $cover = null,
        ?int $startTimestamp = null,
        ?bool $supportsStreaming = null
    ): Future {
        $fields = ['chat_id' => $chatId];
        $fields = $this->applyMediaCaption($fields, $caption, $parseMode, $captionEntities, $showCaptionAboveMedia);

        if ($hasSpoiler !== null)
            $fields['has_spoiler'] = $hasSpoiler;
        if ($duration !== null)
            $fields['duration'] = $duration;
        if ($width !== null)
            $fields['width'] = $width;
        if ($height !== null)
            $fields['height'] = $height;
        if ($startTimestamp !== null)
            $fields['start_timestamp'] = $startTimestamp;
        if ($supportsStreaming !== null)
            $fields['supports_streaming'] = $supportsStreaming;
        if ($keyboard !== null)
            $fields['reply_markup'] = json_encode($keyboard);
        if ($extraParams !== null)
            $fields = array_merge($fields, $extraParams);

        $files = [];
        if ($thumbnail instanceof Media) {
            $files['thumbnail'] = $thumbnail->filePath;
        } elseif ($thumbnail !== null) {
            $fields['thumbnail'] = $thumbnail;
        }

        if ($cover instanceof Media) {
            $files['cover'] = $cover->filePath;
        } elseif ($cover !== null) {
            $fields['cover'] = $cover;
        }

        if ($video instanceof Media) {
            $files['video'] = $video->filePath;
        } else {
            $fields['video'] = $video;
        }

        return $files
            ? $this->requestWithFile('sendVideo', $fields, $files)
            : $this->request('sendVideo', $fields);
    }

    /**
     * Send audio (music or voice).
     */
    public function sendAudio(
        int|string $chatId,
        string|Media $audio,
        ?string $caption = null,
        ?array $keyboard = null,
        ?array $extraParams = null,
        ?string $parseMode = null,
        ?array $captionEntities = null,
        ?int $duration = null,
        ?string $performer = null,
        ?string $title = null,
        Media|string|null $thumbnail = null
    ): Future {
        $fields = ['chat_id' => $chatId];
        $fields = $this->applyMediaCaption($fields, $caption, $parseMode, $captionEntities, null);

        if ($duration !== null)
            $fields['duration'] = $duration;
        if ($performer !== null)
            $fields['performer'] = $performer;
        if ($title !== null)
            $fields['title'] = $title;
        if ($keyboard !== null)
            $fields['reply_markup'] = json_encode($keyboard);
        if ($extraParams !== null)
            $fields = array_merge($fields, $extraParams);

        $files = [];
        if ($thumbnail instanceof Media) {
            $files['thumbnail'] = $thumbnail->filePath;
        } elseif ($thumbnail !== null) {
            $fields['thumbnail'] = $thumbnail;
        }

        if ($audio instanceof Media) {
            $files['audio'] = $audio->filePath;
        } else {
            $fields['audio'] = $audio;
        }

        return $files
            ? $this->requestWithFile('sendAudio', $fields, $files)
            : $this->request('sendAudio', $fields);
    }

    /**
     * Send document (pdf, zip, etc).
     */
    public function sendDocument(
        int|string $chatId,
        string|Media $document,
        ?string $caption = null,
        ?array $keyboard = null,
        ?array $extraParams = null,
        ?string $parseMode = null,
        ?array $captionEntities = null,
        Media|string|null $thumbnail = null,
        ?bool $disableContentTypeDetection = null
    ): Future {
        $fields = ['chat_id' => $chatId];
        $fields = $this->applyMediaCaption($fields, $caption, $parseMode, $captionEntities, null);

        if ($disableContentTypeDetection !== null)
            $fields['disable_content_type_detection'] = $disableContentTypeDetection;
        if ($keyboard !== null)
            $fields['reply_markup'] = json_encode($keyboard);
        if ($extraParams !== null)
            $fields = array_merge($fields, $extraParams);

        $files = [];
        if ($thumbnail instanceof Media) {
            $files['thumbnail'] = $thumbnail->filePath;
        } elseif ($thumbnail !== null) {
            $fields['thumbnail'] = $thumbnail;
        }

        if ($document instanceof Media) {
            $files['document'] = $document->filePath;
        } else {
            $fields['document'] = $document;
        }

        return $files
            ? $this->requestWithFile('sendDocument', $fields, $files)
            : $this->request('sendDocument', $fields);
    }

    /**
     * Send animation (GIF).
     */
    public function sendAnimation(
        int|string $chatId,
        string|Media $animation,
        ?string $caption = null,
        ?array $keyboard = null,
        ?array $extraParams = null,
        ?string $parseMode = null,
        ?array $captionEntities = null,
        ?bool $showCaptionAboveMedia = null,
        ?bool $hasSpoiler = null,
        ?int $duration = null,
        ?int $width = null,
        ?int $height = null,
        Media|string|null $thumbnail = null
    ): Future {
        $fields = ['chat_id' => $chatId];
        $fields = $this->applyMediaCaption($fields, $caption, $parseMode, $captionEntities, $showCaptionAboveMedia);

        if ($hasSpoiler !== null)
            $fields['has_spoiler'] = $hasSpoiler;
        if ($duration !== null)
            $fields['duration'] = $duration;
        if ($width !== null)
            $fields['width'] = $width;
        if ($height !== null)
            $fields['height'] = $height;
        if ($keyboard !== null)
            $fields['reply_markup'] = json_encode($keyboard);
        if ($extraParams !== null)
            $fields = array_merge($fields, $extraParams);

        $files = [];
        if ($thumbnail instanceof Media) {
            $files['thumbnail'] = $thumbnail->filePath;
        } elseif ($thumbnail !== null) {
            $fields['thumbnail'] = $thumbnail;
        }

        if ($animation instanceof Media) {
            $files['animation'] = $animation->filePath;
        } else {
            $fields['animation'] = $animation;
        }

        return $files
            ? $this->requestWithFile('sendAnimation', $fields, $files)
            : $this->request('sendAnimation', $fields);
    }

    /**
     * Send sticker.
     *
     * @param string|null $emoji Emoji associated with the sticker; only for just uploaded stickers
     */
    public function sendSticker(
        int|string $chatId,
        string|Media $sticker,
        ?string $emoji = null,
        ?array $extraParams = null
    ): Future {
        $fields = ['chat_id' => $chatId];

        if ($emoji !== null)
            $fields['emoji'] = $emoji;
        if ($extraParams !== null)
            $fields = array_merge($fields, $extraParams);

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
        int|string $chatId,
        string|Media $voice,
        ?string $caption = null,
        ?array $keyboard = null,
        ?array $extraParams = null,
        ?string $parseMode = null,
        ?array $captionEntities = null,
        ?int $duration = null
    ): Future {
        $fields = ['chat_id' => $chatId];
        $fields = $this->applyMediaCaption($fields, $caption, $parseMode, $captionEntities, null);

        if ($duration !== null)
            $fields['duration'] = $duration;
        if ($keyboard !== null)
            $fields['reply_markup'] = json_encode($keyboard);
        if ($extraParams !== null)
            $fields = array_merge($fields, $extraParams);

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
        int|string $chatId,
        string|Media $videoNote,
        ?array $keyboard = null,
        ?array $extraParams = null,
        ?int $duration = null,
        ?int $length = null,
        Media|string|null $thumbnail = null
    ): Future {
        $fields = ['chat_id' => $chatId];

        if ($duration !== null)
            $fields['duration'] = $duration;
        if ($length !== null)
            $fields['length'] = $length;
        if ($keyboard !== null)
            $fields['reply_markup'] = json_encode($keyboard);
        if ($extraParams !== null)
            $fields = array_merge($fields, $extraParams);

        $files = [];
        if ($thumbnail instanceof Media) {
            $files['thumbnail'] = $thumbnail->filePath;
        } elseif ($thumbnail !== null) {
            $fields['thumbnail'] = $thumbnail;
        }

        if ($videoNote instanceof Media) {
            $files['video_note'] = $videoNote->filePath;
        } else {
            $fields['video_note'] = $videoNote;
        }

        return $files
            ? $this->requestWithFile('sendVideoNote', $fields, $files)
            : $this->request('sendVideoNote', $fields);
    }

    /**
     * Send a group of photos, videos, documents or audios as an album.
     *
     * @param array $mediaItems Array of Media, strings, or InputMedia arrays
     * @param array|null $replyParameters ReplyParameters payload
     */
    public function sendMediaGroup(
        int|string $chatId,
        array $mediaItems,
        ?string $caption = null,
        ?bool $disableNotification = null,
        ?array $replyParameters = null,
        ?array $extraParams = null,
        ?bool $protectContent = null,
        ?bool $allowPaidBroadcast = null,
        ?string $messageEffectId = null,
        ?string $businessConnectionId = null,
        ?int $messageThreadId = null,
        ?int $directMessagesTopicId = null
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

        if ($disableNotification !== null)
            $payload['disable_notification'] = $disableNotification;
        if ($replyParameters !== null)
            $payload['reply_parameters'] = json_encode($replyParameters);
        if ($protectContent !== null)
            $payload['protect_content'] = $protectContent;
        if ($allowPaidBroadcast !== null)
            $payload['allow_paid_broadcast'] = $allowPaidBroadcast;
        if ($messageEffectId !== null)
            $payload['message_effect_id'] = $messageEffectId;
        if ($businessConnectionId !== null)
            $payload['business_connection_id'] = $businessConnectionId;
        if ($messageThreadId !== null)
            $payload['message_thread_id'] = $messageThreadId;
        if ($directMessagesTopicId !== null)
            $payload['direct_messages_topic_id'] = $directMessagesTopicId;

        if ($extraParams) {
            $payload += $extraParams;
        }

        return $hasLocalFile
            ? $this->requestWithFile('sendMediaGroup', $payload, $attachments)
            : $this->request('sendMediaGroup', $payload);
    }

    /**
     * Detect the media type from a file extension for sendMediaGroup.
     */
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
