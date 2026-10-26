# Neili — Documentation

Complete reference for the **Neili** async-first Telegram Bot library.

## Table of Contents

- [Introduction](#introduction)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
  - [Settings Reference](#settings-reference)
  - [Custom HTTP Client](#custom-http-client)
- [Quick Start](#quick-start)
- [Delivery Modes](#delivery-modes)
  - [Long Polling](#long-polling)
  - [Standard Webhook](#standard-webhook)
  - [Multi-Process Webhook](#multi-process-webhook)
  - [Fast-Ack Webhook](#fast-ack-webhook)
  - [Choosing a Mode](#choosing-a-mode)
- [Poller](#poller)
  - [Lifecycle](#poller-lifecycle)
  - [Global Handlers](#poller-global-handlers)
  - [Type-Specific Handlers](#poller-type-handlers)
  - [Error Handling](#poller-error-handling)
  - [Concurrency](#poller-concurrency)
- [KeyboardBuilder](#keyboardbuilder)
- [Media](#media)
- [File Downloads](#file-downloads)
- [Client Methods Reference](#client-methods-reference)
  - [Messages](#messages)
  - [Media](#media-sending)
  - [Chats](#chats)
  - [Chat Members](#chat-members)
  - [Stickers](#stickers)
  - [Forum Topics](#forum-topics)
  - [Bot Profile](#bot-profile)
  - [Payments & Stars](#payments--stars)
  - [Games](#games)
  - [Inline Queries](#inline-queries)
  - [Webhooks & Updates](#webhooks--updates)
  - [Stories](#stories)
  - [Business Accounts](#business-accounts)
  - [Gifts](#gifts)
  - [Verification](#verification)
  - [Managed Bots](#managed-bots)
  - [Suggested Posts](#suggested-posts)
  - [Ephemeral Messages](#ephemeral-messages)
  - [Rich Messages](#rich-messages)
  - [Telegram Passport](#telegram-passport)
  - [Dynamic Method Calls](#dynamic-method-calls)
- [Error Handling](#error-handling)
  - [Exception Hierarchy](#exception-hierarchy)
  - [Catching Errors](#catching-errors)
- [License](#license)

---

## Introduction

Neili wraps the Telegram Bot API with Amp-based asynchronous primitives. It ships:

- A non-blocking HTTP client built on `amphp/http-client`
- Wrappers for the Bot API methods (messages, media, chats, payments, business accounts, stories, …)
- A long-polling `Poller` with exponential backoff + jitter, error classification, and concurrency control
- A webhook helper that supports three delivery modes: standard, multi-process, and fast-ack
- A fluent `KeyboardBuilder` for reply and inline keyboards
- A `Media` helper for local file uploads with automatic `attach://` mapping

---

## Requirements

| Item | Version / Notes |
| --- | --- |
| PHP | `>= 8.1` |
| `amphp/amp` | `^3` |
| `amphp/file` | `^3.2` |
| `amphp/http-client` | `^5.3` |
| PHP extensions | `fileinfo` (required); `posix` (optional, recommended) |
| PHP function `exec()` | Required **only** for multi-process webhook mode |

---

## Installation

```bash
composer require afaztech/neili
```

Autoloading uses PSR-4 (`Neili\` → `src/`).

---

## Configuration

All configuration lives in `Neili\Settings`. It has no constructor arguments — just fluent setters and matching getters.

```php
use Neili\Settings;

$settings = (new Settings())
    ->setAccessToken('123456:ABC-DEF...')
    ->setApiUrl('https://api.telegram.org/bot')
    ->setTimeout(15, 5)
    ->setPollerTimeout(5)
    ->setPollerBackoffBase(1)
    ->setPollerMaxBackoff(32)
    ->setPollerMaxConcurrency(50);

$client = new \Neili\Client($settings);
```

### Settings Reference

| Setter | Getter | Type | Default | Description |
| --- | --- | --- | --- | --- |
| `setAccessToken(string)` | `getAccessToken()` | `string` | *(required)* | Telegram bot access token |
| `setApiUrl(string)` | `getApiUrl()` | `string` | `https://api.telegram.org/bot` | Base API URL (self-hosted Bot API supported) |
| `setApiVerifySSL(bool)` | `isApiVerifySSL()` | `bool` | `true` | Enable TLS verification |
| `setTimeout(int, ?int)` | `getTimeout()` / `getConnectionTimeout()` | `int` | `10` / `5` | Transfer timeout (sec) and connection timeout (sec) |
| `setMultiProcess(bool)` | `isMultiProcess()` | `bool` | `false` | Fork each webhook update into a PHP CLI worker. Mutually exclusive with `setFastAck(true)`. Throws `RuntimeException` if `exec()` is disabled |
| `setFastAck(bool)` | `isFastAck()` | `bool` | `false` | Reply `200 OK` to Telegram immediately, then continue processing. Mutually exclusive with `setMultiProcess(true)` |
| `setPhpBinary(string)` | `getPhpBinary()` | `string` | `/usr/bin/php` | Path to PHP CLI binary for multi-process workers |
| `setPollerTimeout(int)` | `getPollerTimeout()` | `int` | `3` | Telegram long-poll timeout in seconds |
| `setPollerBackoffBase(int)` | `getPollerBackoffBase()` | `int` | `1` | Exponential backoff base (sec) |
| `setPollerMaxBackoff(int)` | `getPollerMaxBackoff()` | `int` | `32` | Maximum backoff delay (sec) |
| `setPollerMaxConcurrency(?int)` | `getPollerMaxConcurrency()` | `?int` | `null` | Max concurrent async update handlers (`null` = unlimited) |
| `setPollerAllowedUpdates(array)` | `getPollerAllowedUpdates()` | `array` | Full list of supported types | Update types requested from Telegram by the poller |
| `setHttpClient(object)` | `getHttpClient()` | `?object` | `null` | Inject a pre-built HTTP client |

### Custom HTTP Client

Inject any object exposing a `request(Request, ?Cancellation): Response` method. Neili applies per-request timeouts on top of it.

```php
use Amp\Http\Client\HttpClientBuilder;

$http = HttpClientBuilder::buildDefault();

$settings = (new Settings())
    ->setAccessToken('YOUR_BOT_TOKEN')
    ->setHttpClient($http);
```

Runtime swap is also supported:

```php
$client->setHttpClient($newHttpClient);
$client->reconnect(); // rebuilds the default client from Settings
```

---

## Quick Start

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Neili\Client;
use Neili\Poller;
use Neili\Settings;

$settings = (new Settings())->setAccessToken('YOUR_BOT_TOKEN');
$client   = new Client($settings);
$poller   = new Poller($client);

$poller->onError(function (\Throwable $e, array $update, string $type) {
    fwrite(STDERR, "[{$type}] {$e->getMessage()}\n");
});

$poller->onMessage(function (array $update) use ($client) {
    $chatId = $update['message']['chat']['id'] ?? null;
    $text   = $update['message']['text'] ?? null;
    if ($chatId && $text) {
        $client->sendMessage($chatId, "Echo: {$text}");
    }
});

$poller->start();
```

---

## Delivery Modes

Neili offers four delivery modes. Choose one per deployment.

| Mode | Setup | Mechanism | Requirements |
| --- | --- | --- | --- |
| **Long polling** | `Poller` + CLI worker | Bot calls `getUpdates` in a loop | Long-running PHP process |
| **Standard webhook** | `handleUpdate()` | Script replies when it finishes | Any HTTP server |
| **Multi-process** | `setMultiProcess(true)` | Reply immediately + fork per update | `exec()` + PHP CLI |
| **Fast-ack** | `setFastAck(true)` | Reply immediately + detach response | PHP-FPM / LiteSpeed / FrankenPHP (best effort elsewhere) |

### Long Polling

```php
use Neili\Client;
use Neili\Poller;
use Neili\Settings;

$client = new Client((new Settings())->setAccessToken('YOUR_BOT_TOKEN'));
$poller = new Poller($client);

$poller->onMessage(function (array $update) use ($client) {
    $update['message']['text'] ?? null
        and $client->sendMessage($update['message']['chat']['id'], 'Echo: ' . $update['message']['text']);
});

$poller->start(); // blocks
```

### Standard Webhook

```php
use Neili\Client;
use Neili\Settings;

$client = new Client((new Settings())->setAccessToken('YOUR_BOT_TOKEN'));

$update = $client->handleUpdate('YOUR_SECRET_TOKEN');

if (isset($update['message']['chat']['id'], $update['message']['text'])) {
    $client->sendMessage(
        $update['message']['chat']['id'],
        'Echo: ' . $update['message']['text']
    )->await();
}
```

Register the webhook once:

```php
$client->setWebhook(
    url: 'https://example.com/webhook.php',
    allowedUpdates: ['message', 'callback_query'],
    secretToken: 'YOUR_SECRET_TOKEN',
    dropPendingUpdates: true,
)->await();
```

### Multi-Process Webhook

Forks each update into its own PHP CLI worker. Best for long-running handlers on a shared host without a persistent worker.

```php
use Neili\Client;
use Neili\Settings;

$settings = (new Settings())
    ->setAccessToken('YOUR_BOT_TOKEN')
    ->setMultiProcess(true)
    ->setPhpBinary('/usr/bin/php');

$client = new Client($settings);

// Replies 200 OK to Telegram immediately, then forks.
$client->handleUpdate('YOUR_SECRET_TOKEN');
```

The child process re-enters `handleUpdate()` in CLI mode, reads the base64-encoded update from `$argv[1]`, and runs the handler. Errors thrown inside the worker are the consumer's responsibility.

### Fast-Ack Webhook

Replies `200 OK` to Telegram as soon as possible, detaches the response, and continues in the same PHP request.

```php
use Neili\Client;
use Neili\Settings;

$settings = (new Settings())
    ->setAccessToken('YOUR_BOT_TOKEN')
    ->setFastAck(true);

$client = new Client($settings);

$update = $client->handleUpdate('YOUR_SECRET_TOKEN');

// Telegram has already received its 200 OK. Slow work continues below.
$chatId = $update['message']['chat']['id'] ?? null;
if ($chatId) {
    $client->sendChatAction($chatId, 'typing')->await();
    $client->sendMessage($chatId, 'Working on it…')->await();
}
```

Detachment uses `fastcgi_finish_request()`, `litespeed_finish_request()`, or `frankenphp_finish_request()`. On other SAPIs it falls back to flushing the output buffer and behaves like a standard webhook.

> **Note:** `setFastAck(true)` and `setMultiProcess(true)` are mutually exclusive. Enabling both throws `RuntimeException`.

### Choosing a Mode

| You have… | Recommended mode |
| --- | --- |
| A long-running CLI worker | Long polling |
| Any HTTP server, short handlers | Standard webhook |
| PHP-FPM, medium handlers, want simplicity | Fast-ack |
| PHP-FPM, long handlers, want isolation | Multi-process |
| Shared hosting, no `exec()` | Fast-ack (if `*_finish_request` available) or standard |

---

## Poller

`Neili\Poller` handles the long-polling loop, error classification, and concurrency.

```php
$poller = new Poller($client);
```

### Poller Lifecycle

| Method | Signature | Description |
| --- | --- | --- |
| `start` | `start(bool $discardOldUpdates = true): void` | Blocks; runs the loop. When `$discardOldUpdates` is true, a short-poll (`timeout=0`) fetch is issued first |
| `stop` | `stop(): void` | Signals the loop to exit after the current iteration |
| `isRunning` | `isRunning(): bool` | `true` while the loop is active |

### Poller Global Handlers

| Method | Description |
| --- | --- |
| `onUpdate(callable $cb)` | Fires for **every** update, in addition to type-specific handlers |
| `onError(callable $cb)` | Signature `fn(\Throwable $e, array $update, string $type): void`. The only error-observation channel. Exceptions thrown inside it are swallowed |

### Poller Type Handlers

`onMessage`, `onEditedMessage`, `onMessageReaction`, `onMessageReactionCount`, `onChatBoost`, `onRemovedChatBoost`, `onChannelPost`, `onEditedChannelPost`, `onInlineQuery`, `onChosenInlineResult`, `onCallbackQuery`, `onShippingQuery`, `onPreCheckoutQuery`, `onPoll`, `onPollAnswer`, `onMyChatMember`, `onChatMember`, `onChatJoinRequest`, `onBusinessMessage`, `onEditedBusinessMessage`, `onDeletedBusinessMessage`, `onBusinessConnection`, `onGuestMessage`, `onPurchasedPaidMedia`, `onManagedBot`, `onSubscription`, `onStoppedMessageGeneration`.

Each accepts `callable(array $update): void`. Multiple callbacks can be registered for the same type.

### Poller Error Handling

| Exception / Source | Reaches `onError()`? | Behavior |
| --- | --- | --- |
| Handler exception | ✅ Yes | Reported; loop continues |
| `RateLimitException` | ✅ Yes | Reported; sleeps `retry_after` seconds (bypasses backoff) |
| `TransientException` | ✅ Yes | Reported; exponential backoff + jitter. After 3 consecutive failures the HTTP client is rebuilt |
| Reconnect failure | ✅ Yes (`type='poller'`) | Reported; loop continues |
| Any other `Throwable` | ✅ Yes | Treated as transient |
| `PermanentException` | ❌ No | Poller stops and rethrows from `start()` |

### Poller Concurrency

If `setPollerMaxConcurrency(n)` is configured, handler execution is gated by a `LocalSemaphore` of size `n`. Each update runs in its own async fiber, so slow handlers do not block polling.

```php
$settings->setPollerMaxConcurrency(50);
```

---

## KeyboardBuilder

`Neili\KeyboardBuilder` is a fluent API for **reply** and **inline** keyboards. Mixing the two kinds throws `RuntimeException`.

```php
use Neili\KeyboardBuilder;

$reply = (new KeyboardBuilder())
    ->row('Yes', 'No')
    ->row('Maybe')
    ->resize(true)
    ->oneTime(false)
    ->persistent(true)
    ->inputFieldPlaceholder('Choose…')
    ->build();

$inline = (new KeyboardBuilder())
    ->inlineRow(['Yes' => 'cb_yes', 'No' => 'cb_no'])
    ->inlineCallbackRow(['More' => 'cb_more'])
    ->build();
```

### Methods

| Method | Signature | Description |
| --- | --- | --- |
| `row` | `row(string ...$buttons): self` | One row of plain reply-keyboard buttons |
| `replyRow` | `replyRow(array $buttons): self` | One row of custom `KeyboardButton` payloads |
| `inlineRow` | `inlineRow(array $textToCallback): self` | Inline buttons using `callback_data` |
| `inlineCallbackRow` | `inlineCallbackRow(array $textToCallback): self` | Alias of `inlineRow` |
| `inlineUrlRow` | `inlineUrlRow(array $textToUrl): self` | Inline buttons with `url` |
| `inlineButtonRow` | `inlineButtonRow(array $buttons): self` | Full inline row from raw payloads |
| `inlineButton` | `inlineButton(array $button): self` | Single inline button as its own row |
| `inline` | `inline(): self` | Forces inline mode without adding rows |
| `resize` | `resize(bool $resize = true): self` | Reply keyboard `resize_keyboard` |
| `oneTime` | `oneTime(bool $oneTime = true): self` | Reply keyboard `one_time_keyboard` |
| `persistent` | `persistent(bool $state = true): self` | Reply keyboard `is_persistent` |
| `inputFieldPlaceholder` | `inputFieldPlaceholder(string): self` | Reply keyboard placeholder (1–64 chars) |
| `selective` | `selective(bool $selective = true): self` | Reply + inline `selective` |
| `forceReply` | `forceReply(bool $state = true): self` | Adds `force_reply` |
| `clear` | `clear(): self` | Resets rows and flags |
| `build` | `build(): array` | Final array for `reply_markup` |

### Static Helpers

```php
KeyboardBuilder::forceReplyObject('Reply to me', selective: false);
KeyboardBuilder::removeKeyboard(true);
```

---

## Media

Wrap local files in a `Media` object. The constructor validates existence and resolves the real path.

```php
use Neili\Media;

$photo = new Media('/path/to/photo.jpg');
echo $photo->filePath; // absolute real path
```

Passing a `Media` to any `send*` or `edit*` method issues a `multipart/form-data` request with `attach://<key>` references.

```php
$client->sendPhoto(123, new Media('/path/to/photo.jpg'), caption: 'Hello!')->await();

$client->sendMediaGroup(123, [
    new Media('/path/to/a.jpg'),
    new Media('/path/to/b.jpg'),
    'https://example.com/remote.jpg',
], caption: 'Album')->await();
```

---

## File Downloads

```php
$info = $client->getFile('FILE_ID')->await();
$remotePath = $info['result']['file_path'];

$url = $client->getFileUrl($remotePath);

$client->downloadFile('FILE_ID', '/path/to/save.bin')->await();
```

`downloadFile()` streams the response body chunk-by-chunk — safe for large files.

---

## Client Methods Reference

Every method returns `Amp\Future`. Use `->await()` in async code or `->map()` / `->catch()` / `->onResolve()` in callback style.

### Messages

| Method | Description |
| --- | --- |
| `sendMessage` | Text message with `parse_mode`, `reply_parameters`, `link_preview_options`, `business_connection_id`, `ephemeral_message_parameters`, etc. |
| `reply` | Shorthand for `sendMessage` with `reply_parameters` |
| `editMessageText` | Edit text or rich message (either `text` or `richMessage` required) |
| `editMessageCaption` | Edit a caption |
| `editMessageMedia` | Replace animation/audio/document/photo/video (supports `Media`) |
| `editMessageLiveLocation` | Edit an ongoing live location |
| `editMessageReplyMarkup` | Replace only the keyboard |
| `stopMessageLiveLocation` | Halt a live location |
| `deleteMessage` / `deleteMessages` | Delete one or many messages |
| `forwardMessage` / `forwardMessages` | Forward messages |
| `copyMessage` / `copyMessages` | Copy messages |
| `setMessageReaction` / `deleteMessageReaction` / `deleteAllMessageReactions` | Manage reactions |
| `sendChatAction` | `typing`, `upload_photo`, `record_video`, … |
| `sendDice` | Animated dice/emoji throw |
| `sendPoll` / `stopPoll` | Polls (options can carry `Media`) |
| `sendVenue` / `sendLocation` / `sendContact` | Location / contact |

```php
$client->sendMessage(
    chatId: 123,
    text: '<b>Hello</b>',
    keyboard: (new KeyboardBuilder())->inlineRow(['OK' => 'cb_ok'])->build(),
    parseMode: 'HTML',
)->await();
```

### Media Sending

| Method | Notes |
| --- | --- |
| `sendPhoto` / `sendLivePhoto` | `caption`, `parse_mode`, `caption_entities`, `show_caption_above_media`, `has_spoiler` |
| `sendPaidMedia` | `star_count` required; rejects empty/invalid media arrays |
| `sendVideo` | `duration`, `width`, `height`, `thumbnail`, `cover`, `supports_streaming` |
| `sendAudio` | `duration`, `performer`, `title`, `thumbnail` |
| `sendDocument` | `thumbnail`, `disable_content_type_detection` |
| `sendAnimation` | `duration`, `width`, `height`, `has_spoiler`, `thumbnail` |
| `sendSticker` | Optional `emoji` |
| `sendVoice` | Optional `duration` |
| `sendVideoNote` | `duration`, `length`, `thumbnail` |
| `sendMediaGroup` | Max 10 items; prefer `reply_parameters` over legacy `reply_to_message_id` |

### Chats

`getChat`, `getChatMemberCount`, `getChatMembersCount` *(deprecated)*, `pinChatMessage`, `unpinChatMessage`, `unpinAllChatMessages`, `setChatTitle`, `setChatDescription`, `setChatPhoto`, `deleteChatPhoto`, `leaveChat`, `setChatPermissions`, `exportChatInviteLink`, `createChatInviteLink`, `editChatInviteLink`, `createChatSubscriptionInviteLink`, `editChatSubscriptionInviteLink`, `revokeChatInviteLink`, `setChatStickerSet`, `deleteChatStickerSet`, `getUserPersonalChatMessages`, `setChatMenuButton`, `getChatMenuButton`.

### Chat Members

`getChatMember`, `getChatAdministrators`, `banChatMember`, `unbanChatMember`, `restrictChatMember`, `promoteChatMember`, `setChatAdministratorCustomTitle`, `setChatMemberTag`, `banChatSenderChat`, `unbanChatSenderChat`, `approveChatJoinRequest`, `declineChatJoinRequest`, `answerChatJoinRequestQuery`, `sendChatJoinRequestWebApp`, `getUserChatBoosts`, `getUserProfilePhotos`.

### Stickers

`getStickerSet`, `uploadStickerFile`, `createNewStickerSet`, `addStickerToSet`, `deleteStickerFromSet`, `setStickerPositionInSet`, `replaceStickerInSet`, `setStickerSetThumbnail`, `setStickerSetTitle`, `deleteStickerSet`, `setCustomEmojiStickerSetThumbnail`, `setStickerEmojiList`, `setStickerKeywords`, `setStickerMaskPosition`, `getCustomEmojiStickers`.

### Forum Topics

`createForumTopic`, `editForumTopic`, `closeForumTopic`, `reopenForumTopic`, `deleteForumTopic`, `unpinAllForumTopicMessages`, `unpinAllGeneralForumTopicMessages`, `getForumTopicIconStickers`, `editGeneralForumTopic`, `closeGeneralForumTopic`, `reopenGeneralForumTopic`, `hideGeneralForumTopic`, `unhideGeneralForumTopic`.

### Bot Profile

`getMe`, `setMyName`, `getMyName`, `setMyDescription`, `getMyDescription`, `setMyShortDescription`, `getMyShortDescription`, `setMyCommands`, `deleteMyCommands`, `getMyCommands`, `setMyDefaultAdministratorRights`, `getMyDefaultAdministratorRights`, `setMyProfilePhoto`, `removeMyProfilePhoto`, `setUserEmojiStatus`, `getUserProfileAudios`, `logOut`, `close`.

### Payments & Stars

`sendInvoice`, `createInvoiceLink`, `answerShippingQuery`, `answerPreCheckoutQuery`, `getMyStarBalance`, `getStarTransactions`, `refundStarPayment`, `editUserStarSubscription`.

For **Telegram Stars** pass `currency: 'XTR'`, `providerToken: ''` and, for subscriptions, `subscriptionPeriod: 2592000` (30 days).

### Games

`sendGame`, `setGameScore`, `getGameHighScores`. Either provide both `chatId` and `messageId`, or `inlineMessageId`.

### Inline Queries

`answerCallbackQuery`, `answerInlineQuery`, `answerWebAppQuery`, `answerGuestQuery`, `savePreparedInlineMessage`, `savePreparedKeyboardButton`.

### Webhooks & Updates

- `handleUpdate(?string $secretToken = null): array`
- `getUpdates(?int $offset, ?int $limit, ?int $timeout, ?array $allowedUpdates, ?array $extraParams): Future`
- `setWebhook`, `deleteWebhook`, `getWebhookInfo`

### Stories

`postStory`, `repostStory`, `editStory`, `deleteStory`.

### Business Accounts

`getBusinessConnection`, `readBusinessMessage`, `deleteBusinessMessages`, `setBusinessAccountName`, `setBusinessAccountUsername`, `setBusinessAccountBio`, `setBusinessAccountProfilePhoto`, `removeBusinessAccountProfilePhoto`, `setBusinessAccountGiftSettings`, `getBusinessAccountStarBalance`, `transferBusinessAccountStars`, `getBusinessAccountGifts`, `convertGiftToStars`, `upgradeGift`, `transferGift`, `sendChecklist`, `editMessageChecklist`.

### Gifts

`getAvailableGifts`, `sendGift`, `giftPremiumSubscription`, `getUserGifts`, `getChatGifts`.

`sendGift` requires **exactly one** of `userId` or `chatId` — otherwise throws `InvalidArgumentException`.

### Verification

`verifyUser`, `verifyChat`, `removeUserVerification`, `removeChatVerification`.

### Managed Bots

`getManagedBotToken`, `replaceManagedBotToken`, `getManagedBotAccessSettings`, `setManagedBotAccessSettings`.

### Suggested Posts

`approveSuggestedPost`, `declineSuggestedPost`.

### Ephemeral Messages

`editEphemeralMessageText`, `editEphemeralMessageMedia`, `editEphemeralMessageCaption`, `editEphemeralMessageReplyMarkup`, `deleteEphemeralMessage`.

### Rich Messages

`sendRichMessage`, `sendRichMessageDraft`, `sendMessageDraft`.

`sendRichMessage` supports embedded `Media` objects — they are uploaded via multipart and rewritten to `attach://` references automatically.

### Telegram Passport

`setPassportDataErrors`.

### Dynamic Method Calls

`Client::__call()` maps any unknown method to a Telegram API call with the first argument as the payload:

```php
// Equivalent to $client->request('getMyStarBalance', [])
$client->getMyStarBalance()->await();
```

---

## Error Handling

### Exception Hierarchy

| Exception | Thrown When | Retry? |
| --- | --- | --- |
| `NeiliException` | Base class for all Neili errors | — |
| `TransientException` | HTTP 5xx, invalid JSON, transport errors (DNS, TCP, TLS, timeouts) | ✅ Yes |
| `RateLimitException` | Telegram returns `error_code: 429` | ✅ After `getRetryAfter()` seconds |
| `PermanentException` | Any other non-OK Telegram response (`400`, `401`, `403`, `404`, …) | ❌ No |

**API surface:**

```php
// RateLimitException
$e->getRetryAfter(): int     // seconds, min 1
$e->getParameters(): array   // e.g. ['retry_after' => 30]

// PermanentException
$e->getErrorCode(): int
$e->getParameters(): array
```

### Catching Errors

```php
use Neili\Exceptions\NeiliException;
use Neili\Exceptions\PermanentException;
use Neili\Exceptions\RateLimitException;
use Neili\Exceptions\TransientException;

try {
    $client->sendMessage(123, 'hi')->await();
} catch (RateLimitException $e) {
    sleep($e->getRetryAfter());
} catch (TransientException $e) {
    // backoff and retry
} catch (PermanentException $e) {
    // do not retry
} catch (NeiliException $e) {
    // catch-all
}
```

Neili does **not** ship an internal logger. Errors surface exclusively through:

1. The exception hierarchy — every `Client` method rejects its `Future` with one of the exceptions above.
2. `Poller::onError(callable)` — the only way to observe errors from inside the poll loop.

---

## License

This project is licensed under the [MIT License](LICENSE).
EOF