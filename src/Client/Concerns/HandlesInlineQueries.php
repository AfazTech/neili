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

trait HandlesInlineQueries
{
    /**
     * Answer callback query (from inline keyboards)
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, ?bool $showAlert = false, ?array $extraParams = null): Future
    {
        $payload = ['callback_query_id' => $callbackQueryId];
        if ($text !== null)
            $payload['text'] = $text;
        if ($showAlert !== null)
            $payload['show_alert'] = $showAlert;
        return $this->request('answerCallbackQuery', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Answer inline query (used in inline bots)
     */
    public function answerInlineQuery(string $inlineQueryId, array $results, ?bool $cacheTime = null, ?bool $isPersonal = null, ?array $extraParams = null): Future
    {
        $payload = ['inline_query_id' => $inlineQueryId, 'results' => json_encode($results)];
        if ($cacheTime !== null)
            $payload['cache_time'] = $cacheTime;
        if ($isPersonal !== null)
            $payload['is_personal'] = $isPersonal;
        return $this->request('answerInlineQuery', $payload + ($extraParams ?? []));
    }

    /**
     * Set the result of an interaction with a Web App.
     */
    public function answerWebAppQuery(
        string $webAppQueryId,
        array $result,
        ?array $extraParams = null
    ): Future {
        $payload = [
            'web_app_query_id' => $webAppQueryId,
            'result' => json_encode($result)
        ];

        return $this->request('answerWebAppQuery', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }
}
