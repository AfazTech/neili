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

trait HandlesStories
{
    /**
     * Post a story on behalf of a managed business account.
     *
     * @param array $content InputStoryContent payload
     */
    public function postStory(
        string $businessConnectionId,
        array $content,
        int $activePeriod,
        ?string $caption = null,
        ?array $areas = null,
        ?bool $postToChatPage = null,
        ?bool $protectContent = null,
        ?array $extraParams = null
    ): Future {
        $payload = [
            'business_connection_id' => $businessConnectionId,
            'content' => json_encode($content),
            'active_period' => $activePeriod,
        ];

        if ($caption !== null) {
            $payload['caption'] = $caption;
        }
        if ($areas !== null) {
            $payload['areas'] = json_encode($areas);
        }
        if ($postToChatPage !== null) {
            $payload['post_to_chat_page'] = $postToChatPage;
        }
        if ($protectContent !== null) {
            $payload['protect_content'] = $protectContent;
        }

        return $this->request('postStory', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Repost a story on behalf of a business account from another business account.
     */
    public function repostStory(
        string $businessConnectionId,
        int $fromChatId,
        int $fromStoryId,
        int $activePeriod,
        ?bool $postToChatPage = null,
        ?bool $protectContent = null,
        ?array $extraParams = null
    ): Future {
        $payload = [
            'business_connection_id' => $businessConnectionId,
            'from_chat_id' => $fromChatId,
            'from_story_id' => $fromStoryId,
            'active_period' => $activePeriod,
        ];

        if ($postToChatPage !== null) {
            $payload['post_to_chat_page'] = $postToChatPage;
        }
        if ($protectContent !== null) {
            $payload['protect_content'] = $protectContent;
        }

        return $this->request('repostStory', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Edit a story previously posted by the bot on behalf of a managed business account.
     */
    public function editStory(
        string $businessConnectionId,
        int $storyId,
        array $content,
        ?string $caption = null,
        ?array $areas = null,
        ?array $extraParams = null
    ): Future {
        $payload = [
            'business_connection_id' => $businessConnectionId,
            'story_id' => $storyId,
            'content' => json_encode($content),
        ];

        if ($caption !== null) {
            $payload['caption'] = $caption;
        }
        if ($areas !== null) {
            $payload['areas'] = json_encode($areas);
        }

        return $this->request('editStory', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Delete a story previously posted by the bot on behalf of a managed business account.
     */
    public function deleteStory(string $businessConnectionId, int $storyId): Future
    {
        return $this->request('deleteStory', [
            'business_connection_id' => $businessConnectionId,
            'story_id' => $storyId,
        ]);
    }
}
