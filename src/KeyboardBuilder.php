<?php

/**
 * @author Abolfazl Majidi (Afaz)
 * @package neili
 * @license https://opensource.org/licenses/MIT
 * @link https://github.com/AfazTech/neili
 */

declare(strict_types=1);

namespace Neili;

/**
 * Fluent builder for Telegram reply and inline keyboards.
 *
 * Only one keyboard kind may be used per builder instance: reply or inline.
 * Mixing them raises a RuntimeException to catch mistakes early.
 */
class KeyboardBuilder
{
    /**
     * Rows for the reply keyboard
     */
    private array $rows = [];

    /**
     * Rows for the inline keyboard
     */
    private array $inlineRows = [];

    /**
     * Whether the builder produces an inline keyboard
     */
    private bool $isInline = false;

    /**
     * Requests clients to resize the reply keyboard vertically
     */
    private bool $resize = true;

    /**
     * Requests clients to hide the reply keyboard once used
     */
    private bool $oneTime = false;

    /**
     * Requests clients to always show the reply keyboard
     */
    private bool $isPersistent = false;

    /**
     * Placeholder shown in the input field while the reply keyboard is active
     */
    private ?string $inputFieldPlaceholder = null;

    /**
     * Show the keyboard to @mentioned users / reply target only
     */
    private bool $selective = false;

    /**
     * Show the reply interface as if the user tapped "Reply"
     */
    private bool $forceReply = false;

    /**
     * Add a row of simple text buttons to the reply keyboard.
     */
    public function row(string ...$buttons): self
    {
        if ($this->isInline) {
            throw new \RuntimeException('Cannot mix reply rows with an inline keyboard. Use inline* methods.');
        }
        $this->rows[] = array_map(static fn(string $text): array => ['text' => $text], $buttons);
        return $this;
    }

    /**
     * Add a fully customised row of reply keyboard buttons.
     *
     * Each element must be a KeyboardButton payload, for example:
     *   ['text' => 'Share', 'request_users' => ['request_id' => 1]]
     *   ['text' => 'Send Contact', 'request_contact' => true]
     *   ['text' => 'Open App', 'web_app' => ['url' => 'https://...']]
     *
     * @param array<int, array<string, mixed>> $buttons
     */
    public function replyRow(array $buttons): self
    {
        if ($this->isInline) {
            throw new \RuntimeException('Cannot mix reply rows with an inline keyboard. Use inline* methods.');
        }
        $this->rows[] = array_values($buttons);
        return $this;
    }

    /**
     * Add a row of inline buttons with callback data.
     *
     * @param array<string, string> $buttons Map of button label to callback_data
     */
    public function inlineRow(array $buttons): self
    {
        $row = [];
        foreach ($buttons as $text => $callbackData) {
            $row[] = ['text' => $text, 'callback_data' => $callbackData];
        }
        $this->inlineRows[] = $row;
        $this->isInline = true;
        return $this;
    }

    /**
     * Alias of inlineRow(); provided for symmetry with replyRow().
     *
     * @param array<string, string> $buttons Map of button label to callback_data
     */
    public function inlineCallbackRow(array $buttons): self
    {
        return $this->inlineRow($buttons);
    }

    /**
     * Add a row of inline buttons that open URLs.
     *
     * @param array<string, string> $buttons Map of button label to URL
     */
    public function inlineUrlRow(array $buttons): self
    {
        $row = [];
        foreach ($buttons as $text => $url) {
            $row[] = ['text' => $text, 'url' => $url];
        }
        $this->inlineRows[] = $row;
        $this->isInline = true;
        return $this;
    }

    /**
     * Add a row of fully customised inline keyboard buttons.
     *
     * Each element must be an InlineKeyboardButton payload. This unlocks
     * web_app, login_url, switch_inline_query*, copy_text, callback_game,
     * pay, disabled, icon_custom_emoji_id and style.
     *
     * @param array<int, array<string, mixed>> $buttons
     */
    public function inlineButtonRow(array $buttons): self
    {
        $this->inlineRows[] = array_values($buttons);
        $this->isInline = true;
        return $this;
    }

    /**
     * Add a single customised inline button as its own row.
     *
     * @param array<string, mixed> $button InlineKeyboardButton payload
     */
    public function inlineButton(array $button): self
    {
        $this->inlineRows[] = [$button];
        $this->isInline = true;
        return $this;
    }

    /**
     * Convert the keyboard to inline mode without adding any rows.
     */
    public function inline(): self
    {
        $this->isInline = true;
        return $this;
    }

    /**
     * Set the resize_keyboard option for reply keyboards.
     */
    public function resize(bool $resize = true): self
    {
        $this->resize = $resize;
        return $this;
    }

    /**
     * Set the one_time_keyboard option for reply keyboards.
     */
    public function oneTime(bool $oneTime = true): self
    {
        $this->oneTime = $oneTime;
        return $this;
    }

    /**
     * Set the is_persistent option for reply keyboards.
     */
    public function persistent(bool $state = true): self
    {
        $this->isPersistent = $state;
        return $this;
    }

    /**
     * Set the input_field_placeholder for reply keyboards (1-64 characters).
     */
    public function inputFieldPlaceholder(string $placeholder): self
    {
        $this->inputFieldPlaceholder = $placeholder;
        return $this;
    }

    /**
     * Set the selective option (1-64 characters; reply and inline).
     */
    public function selective(bool $selective = true): self
    {
        $this->selective = $selective;
        return $this;
    }

    /**
     * Request the reply interface, as if the user manually tapped "Reply".
     *
     * Supported on both InlineKeyboardMarkup and ReplyKeyboardMarkup
     * (Bot API 10.3+).
     */
    public function forceReply(bool $state = true): self
    {
        $this->forceReply = $state;
        return $this;
    }

    /**
     * Reset the builder to its initial state.
     */
    public function clear(): self
    {
        $this->rows = [];
        $this->inlineRows = [];
        $this->isInline = false;
        $this->resize = true;
        $this->oneTime = false;
        $this->isPersistent = false;
        $this->inputFieldPlaceholder = null;
        $this->selective = false;
        $this->forceReply = false;
        return $this;
    }

    /**
     * Compile the final keyboard array for the Telegram Bot API.
     */
    public function build(): array
    {
        if ($this->isInline) {
            $result = ['inline_keyboard' => $this->inlineRows];

            if ($this->forceReply) {
                $result['force_reply'] = true;
            }

            return $result;
        }

        $result = [
            'keyboard' => $this->rows,
            'resize_keyboard' => $this->resize,
            'one_time_keyboard' => $this->oneTime,
        ];

        if ($this->isPersistent) {
            $result['is_persistent'] = true;
        }
        if ($this->inputFieldPlaceholder !== null) {
            $result['input_field_placeholder'] = $this->inputFieldPlaceholder;
        }
        if ($this->selective) {
            $result['selective'] = true;
        }
        if ($this->forceReply) {
            $result['force_reply'] = true;
        }

        return $result;
    }

    /**
     * Build a ForceReply object.
     *
     * This is a standalone reply markup that can be passed directly to
     * sendMessage and friends, without building a keyboard.
     */
    public static function forceReplyObject(
        ?string $inputFieldPlaceholder = null,
        ?bool $selective = null
    ): array {
        $result = ['force_reply' => true];

        if ($inputFieldPlaceholder !== null) {
            $result['input_field_placeholder'] = $inputFieldPlaceholder;
        }
        if ($selective !== null) {
            $result['selective'] = $selective;
        }

        return $result;
    }

    /**
     * Build a ReplyKeyboardRemove object.
     */
    public static function removeKeyboard(?bool $selective = null): array
    {
        $result = ['remove_keyboard' => true];

        if ($selective !== null) {
            $result['selective'] = $selective;
        }

        return $result;
    }
}
