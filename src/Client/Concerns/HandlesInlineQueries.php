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

trait HandlesInlineQueries
{
    /**
     * Answer callback query (from inline keyboards).
     *
     * The $url and $cacheTime parameters are placed after $extraParams to
     * preserve the original 4-argument signature.
     */
    public function answerCallbackQuery(
        string $callbackQueryId,
        ?string $text = null,
        ?bool $showAlert = false,
        ?array $extraParams = null,
        ?string $url = null,
        ?int $cacheTime = null
    ): Future {
        $payload = ['callback_query_id' => $callbackQueryId];
        if ($text !== null)
            $payload['text'] = $text;
        if ($showAlert !== null)
            $payload['show_alert'] = $showAlert;
        if ($url !== null)
            $payload['url'] = $url;
        if ($cacheTime !== null)
            $payload['cache_time'] = $cacheTime;
        return $this->request('answerCallbackQuery', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Answer inline query (used in inline bots).
     *
     * The original signature had (…, ?bool $cacheTime, ?bool $isPersonal,
     * ?array $extraParams). New parameters ($nextOffset, $button) are placed
     * AFTER $extraParams to keep positional backward compatibility. The
     * $cacheTime type is preserved as ?bool even though the Bot API expects
     * an integer; changing it would silently break existing callers.
     *
     * @param array $results Array of InlineQueryResult payloads
     */
    public function answerInlineQuery(
        string $inlineQueryId,
        array $results,
        ?bool $cacheTime = null,
        ?bool $isPersonal = null,
        ?array $extraParams = null,
        ?string $nextOffset = null,
        ?array $button = null
    ): Future {
        $payload = [
            'inline_query_id' => $inlineQueryId,
            'results' => json_encode($results),
        ];
        if ($cacheTime !== null)
            $payload['cache_time'] = $cacheTime;
        if ($isPersonal !== null)
            $payload['is_personal'] = $isPersonal;
        if ($nextOffset !== null)
            $payload['next_offset'] = $nextOffset;
        if ($button !== null)
            $payload['button'] = json_encode($button);
        return $this->request('answerInlineQuery', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Set the result of an interaction with a Web App.
     *
     * @param array $result InlineQueryResult payload
     */
    public function answerWebAppQuery(
        string $webAppQueryId,
        array $result,
        ?array $extraParams = null
    ): Future {
        $payload = [
            'web_app_query_id' => $webAppQueryId,
            'result' => json_encode($result),
        ];

        return $this->request('answerWebAppQuery', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Reply to a received guest message.
     *
     * @param array $result InlineQueryResult payload
     */
    public function answerGuestQuery(string $guestQueryId, array $result): Future
    {
        return $this->request('answerGuestQuery', [
            'guest_query_id' => $guestQueryId,
            'result' => json_encode($result),
        ]);
    }

    /**
     * Store a message that can be sent by a user of a Mini App.
     *
     * @param array $result InlineQueryResult payload
     */
    public function savePreparedInlineMessage(
        int $userId,
        array $result,
        ?bool $allowUserChats = null,
        ?bool $allowBotChats = null,
        ?bool $allowGroupChats = null,
        ?bool $allowChannelChats = null,
        ?array $extraParams = null
    ): Future {
        $payload = [
            'user_id' => $userId,
            'result' => json_encode($result),
        ];
        if ($allowUserChats !== null)
            $payload['allow_user_chats'] = $allowUserChats;
        if ($allowBotChats !== null)
            $payload['allow_bot_chats'] = $allowBotChats;
        if ($allowGroupChats !== null)
            $payload['allow_group_chats'] = $allowGroupChats;
        if ($allowChannelChats !== null)
            $payload['allow_channel_chats'] = $allowChannelChats;
        return $this->request('savePreparedInlineMessage', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Store a keyboard button that can be used by a user within a Mini App.
     *
     * @param array $button KeyboardButton payload; must be of type request_users, request_chat, or request_managed_bot
     */
    public function savePreparedKeyboardButton(int $userId, array $button): Future
    {
        return $this->request('savePreparedKeyboardButton', [
            'user_id' => $userId,
            'button' => json_encode($button),
        ]);
    }
}
