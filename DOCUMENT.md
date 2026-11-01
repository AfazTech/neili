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
- [Asynchronous Operations](#asynchronous-operations)
  - [Awaiting Results](#awaiting-results)
  - [Waiting for Pending Operations](#waiting-for-pending-operations)
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
| `amphp/file` | `^4` |
| `amphp/http-client` | `^5.3` |
| Composer | Resolves dependencies and checks required PHP extensions |
| PHP function `exec()` | Required **only** for multi-process webhook mode |

---

## Installation

```bash
composer require afaztech/neili
```

Autoloading uses PSR-4 (`Neili\` → `src/`). Load `vendor/autoload.php` once at your application entry point. Composer installs Amp and Revolt transitively through the declared dependencies.

For development, clone the repository, run `composer install`, then run `composer test`.

---

## Configuration

All configuration lives in `Neili\Settings`. It has no constructor arguments — just fluent setters and matching getters. Set an access token before creating the client. Neili does not automatically read environment variables or load `.env` files; pass values from your application to Settings.

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
| `setApiVerifySSL(bool)` | `isApiVerifySSL()` | `bool` | `true` | Verify TLS certificates and peer names for the built-in HTTP client; custom clients configure TLS themselves |
| `setTimeout(int, ?int)` | `getTimeout()` / `getConnectionTimeout()` | `int` | `10` / `5` | Transfer timeout (sec) and connection timeout (sec) |
| `setMultiProcess(bool)` | `isMultiProcess()` | `bool` | `false` | Fork each webhook update into a PHP CLI worker. Mutually exclusive with `setFastAck(true)`. Throws `RuntimeException` if `exec()` is disabled |
| `setFastAck(bool)` | `isFastAck()` | `bool` | `false` | Reply `200 OK` to Telegram immediately, then continue processing. Mutually exclusive with `setMultiProcess(true)` |
| `setPhpBinary(string)` | `getPhpBinary()` | `string` | `/usr/bin/php` | Path to PHP CLI binary for multi-process workers |
| `setPollerTimeout(int)` | `getPollerTimeout()` | `int` | `3` | Telegram long-poll timeout in seconds |
| `setPollerBackoffBase(int)` | `getPollerBackoffBase()` | `int` | `1` | Exponential backoff base (sec) |
| `setPollerMaxBackoff(int)` | `getPollerMaxBackoff()` | `int` | `32` | Maximum backoff delay (sec) |
| `setPollerMaxConcurrency(?int)` | `getPollerMaxConcurrency()` | `?int` | `100` | Max in-flight update handlers; positive integer or explicit `null` for unlimited |
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

The built-in transport applies `setApiVerifySSL()` when the Client is constructed and whenever `reconnect()` rebuilds it. Verification is enabled by default. If you change the setting on an existing Client, call `reconnect()` to apply it. An injected HTTP client owns its TLS configuration; Neili does not override it. The same transport is used for API requests and file downloads.

Runtime swap is also supported:

```php
$client->setHttpClient($newHttpClient);
$client->reconnect(); // reuses Settings' injected client, or builds a new default client
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
        $client->sendMessage($chatId, "Echo: {$text}")->await();
    }
});

$poller->start(discardOldUpdates: false);
```

---

## Asynchronous Operations

### Awaiting Results

Telegram API methods return `Amp\Future`. Creating a Future schedules work; it does not ensure that a short-lived PHP script will stay alive until the work finishes. `await()` drives the event loop as needed and returns the decoded Telegram response, including `ok` and `result`:

```php
$response = $client->sendMessage($chatId, 'Hello')->await();
$messageId = $response['result']['message_id'];

$client->editMessageText(
    text: 'Updated',
    chatId: $chatId,
    messageId: $messageId,
)->await();
```

Use `await()` for dependent operations and to catch a specific request's error. Inside a poller handler, awaiting requests also lets handler exceptions reach `onError()` and keeps those requests within the handler's concurrency limit. If a handler returns an `Amp\Future`, the Poller awaits it and routes its failure to `onError()`. Requests neither awaited nor returned are detached from the handler.

### Waiting for Pending Operations

Call `$client->flush()` at the end of a webhook script to wait for this client's pending API requests, uploads, and downloads without awaiting each call separately. Independent operations can run concurrently; completion order is not guaranteed. This also works in fast-ack and CLI worker modes.

```php
$client->sendMessage($chatId, 'First');
$client->sendMessage($chatId, 'Second');

try {
    $client->flush();
} catch (\Amp\CompositeException $error) {
    foreach ($error->getReasons() as $reason) {
        error_log($reason->getMessage());
    }
}
```

`flush(): void` drains all pending operations before throwing `Amp\CompositeException`, even if only one fails. Operations created on the same client while flushing are included. An empty or repeated flush is safe; concurrent or nested flush calls on the same client throw `LogicException`.

Completed operations are released automatically. A previously awaited and handled failure is not reported again. Errors from operations that completed before flushing retain normal Amp Future error handling; `flush()` is not a history of past failures. It does not wait for unrelated event-loop tasks or caller-created Future chains. Await those chains explicitly, and do not call `flush()` from inside an operation it is waiting for.


Fast-ack and multi-process modes do not automatically drain Futures. Call `flush()` in the processing script or await the operations explicitly. `flush()` does not retry failed operations and does not return their results.

---

## Delivery Modes

Neili offers four delivery modes. Choose one per deployment. The snippets below assume Composer autoloading has already been loaded. Save webhook code in your HTTP endpoint; do not start the Poller in that endpoint.

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
    if (isset($update['message']['chat']['id'], $update['message']['text'])) {
        $client->sendMessage($update['message']['chat']['id'], 'Echo: ' . $update['message']['text'])->await();
    }
});

$poller->start(); // blocks
```

### Standard Webhook

The endpoint accepts POST requests. If a secret token is configured, it is checked with `hash_equals()`; a missing or incorrect token sets HTTP 403. Other methods set HTTP 405 with `Allow: POST`. Malformed JSON or a payload without a non-negative integer `update_id` sets HTTP 400 and throws `UnexpectedValueException`. Preserve this status if your application catches the exception. Unknown future update fields are retained.

The CLI worker applies the same payload validation and rejects invalid base64. Validation happens before fast-ack or starting a multi-process worker.

```php
use Neili\Client;
use Neili\Settings;

$client = new Client((new Settings())->setAccessToken('YOUR_BOT_TOKEN'));

$update = $client->handleUpdate('YOUR_SECRET_TOKEN');

if (isset($update['message']['chat']['id'], $update['message']['text'])) {
    $client->sendMessage(
        $update['message']['chat']['id'],
        'Echo: ' . $update['message']['text']
    );
}

$client->flush();
```

Register the webhook once from a separate CLI setup script (after creating the client):

```php
$client->setWebhook(
    url: 'https://example.com/webhook.php',
    allowedUpdates: ['message', 'callback_query'],
    secretToken: 'YOUR_SECRET_TOKEN',
    dropPendingUpdates: true,
)->await();
```

### Multi-Process Webhook

Starts a separate PHP CLI process for each update using `exec()`. The same script receives the update as a command-line payload.

```php
use Neili\Client;
use Neili\Settings;

$settings = (new Settings())
    ->setAccessToken('YOUR_BOT_TOKEN')
    ->setMultiProcess(true)
    ->setPhpBinary('/usr/bin/php');

$client = new Client($settings);

// The HTTP parent starts a CLI worker and exits. The worker continues here.
$update = $client->handleUpdate('YOUR_SECRET_TOKEN');

if (isset($update['message']['chat']['id'], $update['message']['text'])) {
    $client->sendMessage($update['message']['chat']['id'], 'Echo: ' . $update['message']['text']);
}

$client->flush();
```

The child process re-enters the same script, reads the base64-encoded update from `$argv[1]` in `handleUpdate()`, and continues with the processing code below it. Worker stdout and stderr are redirected to `/dev/null`; configure application error reporting explicitly. This mode does not provide a durable queue or worker supervision.

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
    $client->sendMessage($chatId, 'Working on it…');
}

$client->flush();
```

Detachment uses `fastcgi_finish_request()`, `litespeed_finish_request()`, or `frankenphp_finish_request()`. On other SAPIs it falls back to flushing the output buffer and behaves like a standard webhook.

> **Note:** `setFastAck(true)` and `setMultiProcess(true)` are mutually exclusive. Enabling both throws `RuntimeException`.

Fast-ack continues to occupy the current server worker until processing finishes. After acknowledgment, processing errors cannot change the response already sent to Telegram.

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
| `stop` | `stop(): void` | Stops accepting new updates; `start()` waits for already started handlers before returning |
| `isRunning` | `isRunning(): bool` | `true` while the loop is active |

### Poller Global Handlers

| Method | Description |
| --- | --- |
| `onUpdate(callable $cb)` | Fires for **every** update, in addition to type-specific handlers |
| `onError(callable $cb)` | Signature `fn(\Throwable $e, array $update, string $type): void`. Reports polling errors and exceptions thrown by handlers. Exceptions thrown inside it are swallowed |

### Poller Type Handlers

`onMessage`, `onEditedMessage`, `onMessageReaction`, `onMessageReactionCount`, `onChatBoost`, `onRemovedChatBoost`, `onChannelPost`, `onEditedChannelPost`, `onInlineQuery`, `onChosenInlineResult`, `onCallbackQuery`, `onShippingQuery`, `onPreCheckoutQuery`, `onPoll`, `onPollAnswer`, `onMyChatMember`, `onChatMember`, `onChatJoinRequest`, `onBusinessMessage`, `onEditedBusinessMessage`, `onDeletedBusinessMessage`, `onBusinessConnection`, `onGuestMessage`, `onPurchasedPaidMedia`, `onManagedBot`, `onSubscription`, `onStoppedMessageGeneration`.

Each accepts a callback receiving `array $update`; it may return `void` or an `Amp\Future`. Multiple callbacks can be registered for the same type.

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

The default limit is **100 in-flight updates**. Set a positive limit before constructing the Poller; explicit `null` disables the limit. Invalid zero or negative limits throw `InvalidArgumentException`.

Capacity is acquired before creating each update fiber. When all slots are occupied, polling pauses instead of spawning fibers waiting for a slot. At most one fetched Telegram batch remains buffered. A slot covers all callbacks for that update, including any returned Future. Slots are released on success and failure.

`stop()` stops dispatching new updates and `start()` drains active handlers before returning, including when polling fails permanently. A handler that never completes can therefore delay shutdown. Do not await the Poller's own `start()` Future inside a handler.

Awaiting non-blocking I/O allows other fibers to progress; blocking PHP calls still block the process. The limit does not cover requests detached by neither awaiting nor returning their Future. Avoid `flush()` inside a handler on the shared polling client: it also tracks polling requests and other handlers' requests.

The offset advances when an update is dispatched. This is not a durable queue or a guarantee that work survives process crashes.
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
| `selective` | `selective(bool $selective = true): self` | Reply-keyboard `selective` (omitted in inline mode) |
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

Pass a `Media` object to parameters whose signature accepts it. Supported methods use `multipart/form-data` and add `attach://<key>` references where needed. A string media value is sent as a Telegram file ID or remote URL; wrap local paths in `Media`. Upload bodies are streamed from disk.

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

Telegram API methods and `downloadFile()` return `Amp\Future`. Use `->await()` when you need a result or dependent ordering, or `->map()` / `->catch()` for callbacks.


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

- `flush(): void` — drain pending client operations; see [flush behavior](#waiting-for-pending-operations)

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
// Use __call directly here to demonstrate a dynamic call with a raw payload.
$client->__call('sendMessage', [['chat_id' => 123, 'text' => 'Hello']])->await();
```

---

## Error Handling

### Exception Hierarchy

| Exception | Thrown When | Retry? |
| --- | --- | --- |
| `NeiliException` | Base class for all Neili errors | — |
| `TransientException` | HTTP 5xx, malformed successful responses, unexpected HTTP statuses, transport errors | ✅ Yes |
| `RateLimitException` | HTTP 429 or Telegram returns `error_code: 429` | ✅ After `getRetryAfter()` seconds |
| `PermanentException` | HTTP 4xx except 429, or a non-OK Telegram response (`400`, `401`, `403`, …) | ❌ No |

**Exception accessors:**

| Exception | Method | Returns |
| --- | --- | --- |
| `RateLimitException` | `getRetryAfter()` | Retry delay in seconds (`int`, at least 1) |
| `RateLimitException` | `getParameters()` | Telegram response parameters (`array`) |
| `PermanentException` | `getErrorCode()` | Telegram error code (`int`) |
| `PermanentException` | `getParameters()` | Telegram response parameters (`array`) |

### Catching Errors

```php
use Neili\Exceptions\NeiliException;
use Neili\Exceptions\PermanentException;
use Neili\Exceptions\RateLimitException;
use Neili\Exceptions\TransientException;

try {
    $client->sendMessage(123, 'hi')->await();
} catch (RateLimitException $e) {
    \Amp\delay($e->getRetryAfter());
    // Retry explicitly if appropriate; waiting alone does not resend the request.
} catch (TransientException $e) {
    // backoff and retry
} catch (PermanentException $e) {
    // do not retry
} catch (NeiliException $e) {
    // catch-all
}
```

Neili does **not** ship an internal logger. Handle errors through awaited Futures, `flush()` (which wraps failures in `Amp\CompositeException`), and `Poller::onError()` for polling and handler exceptions. A Future created without awaiting it inside a handler is not automatically routed to `onError()`.

Outgoing JSON uses strict encoding for both request bodies and nested fields. Invalid UTF-8, recursive arrays, and unsupported JSON values throw `PermanentException` with the original `JsonException` as the cause, before sending the request. Nested-field encoding and argument validation can throw synchronously before a Future is returned. Multipart boolean and structured extra parameters are JSON-encoded as well. File operations may also throw filesystem exceptions, and PHP `Error` instances are not translated into the API exception hierarchy. Keep a final `Throwable` catch at application boundaries if you need to report these failures.

Client requests are not retried automatically. The Poller's backoff policy applies to fetching updates, not to resending failed outgoing messages.

---

## License

This project is licensed under the [MIT License](LICENSE).
