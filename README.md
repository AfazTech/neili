# Neili — Asynchronous Telegram Bot Library for PHP

Neili is an async-first PHP library built on **Amp** that streamlines building robust Telegram bots.
It ships a non-blocking HTTP client, wrappers for the Telegram Bot API, a long-polling `Poller` with exponential backoff, concurrency control and error classification, a multi-process webhook handler, and a fluent keyboard builder.

**If this project helps you, please consider giving it a ⭐ to support future updates and features.**

### AI-Assisted Development

For everything you need to know about this project — including AI-assisted ("vibe") coding — hand the `Neili.txt` file to your AI assistant. It contains the full project structure, file contents, conventions and architecture.

---

## Table of Contents

- [Introduction](#introduction)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
  - [Settings Reference](#settings-reference)
  - [Custom HTTP Client](#custom-http-client)
- [Usage](#usage)
  - [Long Polling](#long-polling)
  - [Webhook](#webhook)
  - [Multi-Process Webhook](#multi-process-webhook)
  - [Polling vs Webhook](#polling-vs-webhook)
- [Error Handling](#error-handling)
  - [Exception Hierarchy](#exception-hierarchy)
  - [Observing Errors](#observing-errors)
  - [Poller Error Policy](#poller-error-policy)
- [Poller Reference](#poller-reference)
- [Keyboard Builder](#keyboard-builder)
- [Media Uploads](#media-uploads)
- [File Downloads](#file-downloads)
- [Client Methods Reference](#client-methods-reference)
  - [Messages](#messages)
  - [Media](#media)
  - [Chat Management](#chat-management)
  - [Chat Members](#chat-members)
  - [Stickers](#stickers)
  - [Forum Topics](#forum-topics)
  - [Bot Profile](#bot-profile)
  - [Payments & Stars](#payments--stars)
  - [Games](#games)
  - [Inline Queries](#inline-queries)
  - [Updates & Webhooks](#updates--webhooks)
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
- [TODO](#todo)
- [License](#license)

---

## Introduction

Neili wraps the Telegram Bot API with Amp-based asynchronous primitives. It offers:

- Safe, non-blocking HTTP requests via `amphp/http-client`
- Async message sending, media uploads, and chat management
- Updates received via long polling **or** webhooks
- Lightweight, framework-agnostic architecture suitable for short-lived webhook endpoints and long-running worker processes
- Streaming file downloads using `amphp/file` (safe for large files)
- Automatic `attach://` mapping for local `Media` uploads inside multipart requests

---

## Features

- Async-first Telegram Bot API wrapper built on the Amp HTTP client
- `Poller` with configurable timeout, exponential backoff + jitter, concurrency limit, and error classification
- Webhook helper compatible with standard PHP and multi-process (`exec()`) setups
- `KeyboardBuilder` fluent API for reply and inline keyboards (with ForceReply / ReplyKeyboardRemove helpers)
- `Media` helper for local file uploads, auto-recognized inside InputMedia, InputPaidMedia, and InputRichMessage payloads
- Streaming `downloadFile()` (64 KiB chunks) — safe for large files served by a local Bot API server
- Zero internal logging: errors are surfaced to the consumer through `Poller::onError()` and through the exception hierarchy
- Full exception taxonomy: `TransientException`, `PermanentException`, `RateLimitException`

---

## Requirements

- **PHP >= 8.1**
- [**amphp/amp** ^3](https://github.com/amphp/amp)
- [**amphp/file** ^3.2](https://github.com/amphp/file) — async file I/O
- [**amphp/http-client** ^5.3](https://github.com/amphp/http-client) — non-blocking HTTP
- PHP extensions: `fileinfo`, `posix` (optional, recommended)
- PHP function `exec()` — only required for **multi-process webhook mode**

---

## Installation

Install via Composer:

```bash
composer require afaztech/neili
```

Or clone the repository:

```bash
git clone https://github.com/afaztech/neili.git
cd neili
composer install
```

Autoloading uses PSR-4 (`Neili\` → `src/`).

---

## Quick Start

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Neili\Client;
use Neili\Poller;
use Neili\Settings;

$settings = (new Settings())
    ->setAccessToken('YOUR_BOT_TOKEN');

$client = new Client($settings);
$poller = new Poller($client);

// Optional: observe errors that escape handlers or the poll loop
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

## Configuration

Neili is configured through a single `Neili\Settings` instance which is passed to the `Client` constructor. `Settings` has no constructor arguments and exposes only fluent setters plus their matching getters.

```php
use Neili\Settings;

$settings = (new Settings())
    ->setAccessToken('123456:ABC-DEF...')       // required
    ->setApiUrl('https://api.telegram.org/bot') // optional
    ->setTimeout(15, 5)                         // transfer timeout, connection timeout
    ->setPollerTimeout(5)
    ->setPollerBackoffBase(1)
    ->setPollerMaxBackoff(32)
    ->setPollerMaxConcurrency(50);

$client = new \Neili\Client($settings);
```

### Settings Reference

| Method | Getter | Type | Default | Description |
| --- | --- | --- | --- | --- |
| `setAccessToken(string)` | `getAccessToken()` | `string` | *(required)* | Telegram bot access token |
| `setApiUrl(string)` | `getApiUrl()` | `string` | `https://api.telegram.org/bot` | Base API URL (self-hosted Bot API supported) |
| `setApiVerifySSL(bool)` | `isApiVerifySSL()` | `bool` | `true` | Enable TLS verification |
| `setTimeout(int, ?int)` | `getTimeout()` / `getConnectionTimeout()` | `int` | `10` / `5` | Transfer timeout (sec) and connection timeout (sec) |
| `setMultiProcess(bool)` | `isMultiProcess()` | `bool` | `false` | Fork webhook updates into separate PHP processes. Throws `RuntimeException` when `exec()` is disabled |
| `setPhpBinary(string)` | `getPhpBinary()` | `string` | `/usr/bin/php` | Path to PHP CLI binary for multi-process workers |
| `setPollerTimeout(int)` | `getPollerTimeout()` | `int` | `3` | Telegram long-poll timeout in seconds |
| `setPollerBackoffBase(int)` | `getPollerBackoffBase()` | `int` | `1` | Exponential backoff base (sec) |
| `setPollerMaxBackoff(int)` | `getPollerMaxBackoff()` | `int` | `32` | Maximum backoff delay (sec) |
| `setPollerMaxConcurrency(?int)` | `getPollerMaxConcurrency()` | `?int` | `null` | Max concurrent async update handlers (`null` = unlimited) |
| `setHttpClient(object)` | `getHttpClient()` | `?object` | `null` | Inject a pre-built HTTP client (must expose a `request()` method) |

### Custom HTTP Client

Inject any client exposing a `request(Request, ?Cancellation): Response` method. The client's timeouts can be overridden per-request by Neili.

```php
use Amp\Http\Client\HttpClientBuilder;

$http = HttpClientBuilder::buildDefault();

$settings = (new Settings())
    ->setAccessToken('YOUR_BOT_TOKEN')
    ->setHttpClient($http);
```

You can also swap the transport at runtime:

```php
$client->setHttpClient($newHttpClient);
$client->reconnect(); // rebuilds the default client from Settings
```

---

## Usage

### Long Polling

```php
use Neili\Client;
use Neili\Poller;
use Neili\Settings;

$settings = (new Settings())->setAccessToken('YOUR_BOT_TOKEN');
$client   = new Client($settings);
$poller   = new Poller($client);

// Optional: observe errors raised inside handlers or by the poll loop
$poller->onError(function (\Throwable $e, array $update, string $type) {
    fwrite(STDERR, "[{$type}] {$e->getMessage()}\n");
});

// Global handler (fires for every update)
$poller->onUpdate(function (array $update) {
    // inspect every update
});

// Type-specific handlers
$poller->onMessage(function (array $update) use ($client) {
    $chatId = $update['message']['chat']['id'] ?? null;
    $text   = $update['message']['text'] ?? null;
    if ($chatId && $text) {
        $client->sendMessage($chatId, "Echo: {$text}");
    }
});

$poller->onCallbackQuery(function (array $update) use ($client) {
    $client->answerCallbackQuery($update['callback_query']['id'], 'Got it!');
});

// Start polling (default: discard pending updates at startup)
$poller->start(discardOldUpdates: true);
```

To stop from inside a handler:

```php
$poller->onMessage(function () use ($poller) {
    $poller->stop();
});
```

### Webhook

```php
use Neili\Client;
use Neili\Settings;

$settings = (new Settings())->setAccessToken('YOUR_BOT_TOKEN');
$client   = new Client($settings);

$update = $client->handleUpdate('YOUR_SECRET_TOKEN');

if (isset($update['message']['chat']['id'], $update['message']['text'])) {
    $client->sendMessage(
        $update['message']['chat']['id'],
        'Echo: ' . $update['message']['text']
    )->await();
}
```

Set the webhook once (from the CLI, or via a setup script):

```php
$client->setWebhook(
    url: 'https://example.com/webhook.php',
    allowedUpdates: ['message', 'callback_query'],
    secretToken: 'YOUR_SECRET_TOKEN',
    dropPendingUpdates: true,
)->await();
```

### Multi-Process Webhook

Enables non-blocking handling of incoming updates on plain PHP-FPM / Apache deployments. Each update is forked into a fresh PHP process via `exec()`.

```php
use Neili\Client;
use Neili\Settings;

$settings = (new Settings())
    ->setAccessToken('YOUR_BOT_TOKEN')
    ->setMultiProcess(true)
    ->setPhpBinary('/usr/bin/php');

$client = new Client($settings);

// In webhook.php — returns immediately after forking
$client->handleUpdate('YOUR_SECRET_TOKEN');
```

The worker reads the base64-encoded update from `$argv[1]` and re-enters `handleUpdate()` in CLI mode. The child process is fire-and-forget; Neili does not attach any error callback to it — the consumer owns whatever happens inside the worker.

### Polling vs Webhook

| Method | Pros | Cons |
| --- | --- | --- |
| Long Polling | Simple, no external server config | Requires a continuously running process |
| Standard Webhook | Easy to integrate with any HTTP server | Single-threaded by default |
| Multi-Process Webhook | Non-blocking, concurrent update handling | Requires PHP CLI and `exec()` |

---

## Error Handling

Every `Client` method returns an `Amp\Future`. When the underlying HTTP request fails or Telegram returns a non-OK payload, Neili throws one of the following exceptions.

### Exception Hierarchy

| Exception | Thrown When | Retry? |
| --- | --- | --- |
| `NeiliException` | Base class for all Neili errors | — |
| `TransientException` | HTTP 5xx, invalid JSON, transport errors (DNS, TCP, TLS, timeouts) | ✅ Yes |
| `RateLimitException` | Telegram returns `error_code: 429` | ✅ After `getRetryAfter()` seconds |
| `PermanentException` | Any other non-OK Telegram response (`400`, `401`, `403`, `404`, …) | ❌ No |

```php
use Neili\Exceptions\NeiliException;
use Neili\Exceptions\PermanentException;
use Neili\Exceptions\RateLimitException;
use Neili\Exceptions\TransientException;

try {
    $client->sendMessage(123, 'hi')->await();
} catch (RateLimitException $e) {
    sleep($e->getRetryAfter());
    // retry
} catch (TransientException $e) {
    // backoff and retry
} catch (PermanentException $e) {
    // do not retry — bad token, chat not found, etc.
    echo $e->getErrorCode();      // e.g. 400
    print_r($e->getParameters()); // raw "parameters" block
} catch (NeiliException $e) {
    // catch-all
}
```

**API surface:**

```php
// RateLimitException
$e->getRetryAfter(): int     // seconds, min 1
$e->getParameters(): array   // e.g. ['retry_after' => 30]

// PermanentException
$e->getErrorCode(): int
$e->getParameters(): array

// TransientException / NeiliException
// Standard \RuntimeException API
```

### Observing Errors

Neili does **not** ship an internal logger. There is no PSR-3 dependency, no `Neili\Logger`, no `error_log()` call inside the library, and no `getLogger()` method on `Settings`. Errors are surfaced exclusively through:

1. **The exception hierarchy** — every `Client` method rejects its `Future` with one of the exceptions above, so callers can `try/catch` around `->await()`.
2. **`Poller::onError(callable)`** — the only way to observe errors that occur **inside the poll loop**, including errors raised by user handlers.

When you use the `Poller`, you decide where errors go: they can be routed to `fwrite(STDERR, ...)`, to any PSR-3 logger you already use in your application, to a metrics counter, or silently ignored.

```php
use Psr\Log\LoggerInterface;

$poller->onError(function (\Throwable $e, array $update, string $type) use ($logger) {
    $logger->error('neili.poller.error', [
        'type'    => $type,
        'message' => $e->getMessage(),
        'update'  => $update,
    ]);
});
```

Because Neili itself has no dependency on PSR-3, the callback is the single integration point — wire it to whatever observability stack you already run.

### Poller Error Policy

`Poller::onError()` receives every error that escapes a handler or that the poll loop decides to recover from. Its signature is:

```php
fn(\Throwable $e, array $update, string $type): void
```

- `$type = 'message'` (or any other detected update type) for a type-specific handler failure.
- `$type = 'onUpdate'` for a failure in the global update handler.
- `$type = 'poller'` for a failure raised by the poll loop itself (getUpdates, reconnect, pre-loop discard). In this case `$update` is `[]`.

| Exception / Source | Reaches `onError()`? | Poller Behavior |
| --- | --- | --- |
| Handler exception (`onMessage`, `onUpdate`, …) | ✅ Yes | Reported; loop continues |
| `RateLimitException` | ✅ Yes | Reported; sleep exactly `retry_after` seconds, then continue (bypasses exponential backoff) |
| `TransientException` | ✅ Yes | Reported; exponential backoff (base × 2^n, capped at `pollerMaxBackoff`, with up to 1 s of jitter) and continue. After **3 consecutive** transient failures the underlying HTTP client is rebuilt via `Client::reconnect()` |
| Reconnect failure | ✅ Yes (`type = 'poller'`) | Reported; loop continues |
| Any other `Throwable` | ✅ Yes | Treated as transient; reported and retried with backoff |
| `PermanentException` in the main loop | ❌ No | Poller stops (`isRunning()` becomes `false`) and the exception is rethrown from `start()` so the caller observes it |
| `PermanentException` during `discardOldUpdates` | ❌ No | Poller stops and the exception is rethrown from `start()`, exactly like the main loop |

A successful `getUpdates` call resets both the failure counter and the consecutive-failure counter used for reconnecting.

**Exceptions thrown by the `onError` callback itself are swallowed** to keep the poll loop alive. This is intentional: a broken observability hook must not take down the bot.

---

## Poller Reference

```php
$poller = new Poller($client);
```

### Lifecycle

| Method | Signature | Description |
| --- | --- | --- |
| `start` | `start(bool $discardOldUpdates = true): void` | Blocks; runs the async polling loop. When `$discardOldUpdates` is true, a **short-poll** (timeout=0) fetch is issued first to skip the backlog |
| `stop` | `stop(): void` | Signals the loop to exit after the current iteration |
| `isRunning` | `isRunning(): bool` | `true` while the poll loop is active |

### Global Handlers

| Method | Description |
| --- | --- |
| `onUpdate(callable $cb)` | Fires for **every** update, in addition to type-specific handlers |
| `onError(callable $cb)` | Signature `fn(\Throwable $e, array $update, string $type): void`. The only error-observation channel Neili offers. Exceptions thrown inside it are swallowed |

### Type-Specific Handlers

`onMessage`, `onEditedMessage`, `onMessageReaction`, `onMessageReactionCount`, `onChatBoost`, `onRemovedChatBoost`, `onChannelPost`, `onEditedChannelPost`, `onInlineQuery`, `onChosenInlineResult`, `onCallbackQuery`, `onShippingQuery`, `onPreCheckoutQuery`, `onPoll`, `onPollAnswer`, `onMyChatMember`, `onChatMember`, `onChatJoinRequest`, `onBusinessMessage`, `onEditedBusinessMessage`, `onDeletedBusinessMessage`, `onBusinessConnection`, `onGuestMessage`, `onPurchasedPaidMedia`, `onManagedBot`, `onSubscription`, `onStoppedMessageGeneration`.

Each accepts a `callable(array $update): void` and multiple callbacks can be registered for the same type.

```php
$poller->onMessage(fn (array $u) => /* ... */);
$poller->onCallbackQuery(fn (array $u) => /* ... */);
$poller->onPreCheckoutQuery(fn (array $u) => /* ... */);
```

### Concurrency

If `setPollerMaxConcurrency(n)` is configured, handler execution is gated by an `Amp\Sync\LocalSemaphore` of size `n`. Each update is dispatched as its own async fiber, so slow handlers do not block the polling loop.

```php
$settings->setPollerMaxConcurrency(50);
```

---

## Keyboard Builder

`Neili\KeyboardBuilder` provides a fluent API for both **reply** and **inline** keyboards. Mixing the two types on the same builder throws a `RuntimeException`.

```php
use Neili\KeyboardBuilder;

// Reply keyboard
$reply = (new KeyboardBuilder())
    ->row('Yes', 'No')
    ->row('Maybe')
    ->resize(true)
    ->oneTime(false)
    ->persistent(true)
    ->inputFieldPlaceholder('Choose…')
    ->build();

// Inline keyboard with callback data
$inline = (new KeyboardBuilder())
    ->inlineRow(['Yes' => 'cb_yes', 'No' => 'cb_no'])
    ->inlineCallbackRow(['More' => 'cb_more'])
    ->build();

// Inline URL buttons
$urls = (new KeyboardBuilder())
    ->inlineUrlRow(['Open' => 'https://example.com'])
    ->build();

// Fully custom inline buttons (web_app, login_url, copy_text, …)
$custom = (new KeyboardBuilder())
    ->inlineButtonRow([
        ['text' => 'Open App', 'web_app' => ['url' => 'https://example.com']],
        ['text' => 'Copy', 'copy_text' => ['text' => 'abc']],
    ])
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
| `forceReply` | `forceReply(bool $state = true): self` | Adds `force_reply` to the built markup |
| `clear` | `clear(): self` | Resets all rows and flags |
| `build` | `build(): array` | Emits the final array for `reply_markup` |

### Static Helpers

```php
KeyboardBuilder::forceReplyObject('Reply to me', selective: false);
// → ['force_reply' => true, 'input_field_placeholder' => 'Reply to me', 'selective' => false]

KeyboardBuilder::removeKeyboard(true);
// → ['remove_keyboard' => true, 'selective' => true]
```

---

## Media Uploads

### `Media`

Wrap local files in a `Media` object. The constructor validates existence and resolves the real path.

```php
use Neili\Media;

$photo = new Media('/path/to/photo.jpg');
echo $photo->filePath; // realpath
```

When any `Media` is passed to a `send*` or `edit*` method, Neili issues a **multipart/form-data** request and rewrites the payload with `attach://<key>` references.

### Uploading Media

```php
use Neili\Media;

$client->sendPhoto(123, new Media('/path/to/photo.jpg'), caption: 'Hello!')->await();

$client->sendMediaGroup(123, [
    new Media('/path/to/a.jpg'),
    new Media('/path/to/b.jpg'),
    'https://example.com/remote.jpg',
], caption: 'Album')->await();

$client->sendDocument(123, new Media('/path/to/report.pdf'))->await();
```

`sendMediaGroup()` accepts a mix of `Media` objects, remote URLs, and raw `InputMedia` arrays. It enforces Telegram's **10-item maximum**.

`sendPaidMedia()` accepts an array of `Media` objects or `InputPaidMedia` arrays, and requires `star_count`:

```php
$client->sendPaidMedia(
    chatId: 123,
    starCount: 100,
    media: [new Media('/path/to/photo.jpg')],
    caption: 'Premium content',
)->await();
```

Methods that transparently recognize `Media` inside their payload:

- `sendLivePhoto`, `sendVideo`, `sendAudio`, `sendDocument`, `sendAnimation`, `sendSticker`, `sendVoice`, `sendVideoNote`
- `sendPoll` (option media + poll media + explanation media)
- `sendRichMessage` (via InputRichMessage → InputMedia)
- `editMessageMedia`, `editEphemeralMessageMedia`
- `setMyProfilePhoto`, `setBusinessAccountProfilePhoto` (auto static/animated based on extension)
- `setChatPhoto`, `setStickerSetThumbnail`, `uploadStickerFile`
- `setWebhook` (certificate upload)

---

## File Downloads

```php
// 1) Get file metadata
$info = $client->getFile('FILE_ID')->await();
$remotePath = $info['result']['file_path'];

// 2) Get a public download URL
$url = $client->getFileUrl($remotePath);
// → https://api.telegram.org/file/bot<TOKEN>/<file_path>

// 3) Stream to disk (64 KiB chunks — safe for large files)
$client->downloadFile('FILE_ID', '/path/to/save.bin')->await();
```

`downloadFile()` streams the response body to disk without buffering the whole file in memory.

---

## Client Methods Reference

All methods return `Amp\Future`. Use `->await()` inside an async context or `->onResolve()` / `->map()` / `->catch()` for callback style.

### Messages

| Method | Description |
| --- | --- |
| `sendMessage(int\|string $chatId, string $text, ...)` | Send a text message. Supports `parse_mode`, `entities`, `reply_parameters`, `link_preview_options`, `message_thread_id`, `direct_messages_topic_id`, `business_connection_id`, `suggested_post_parameters`, `ephemeral_message_parameters`, etc. |
| `reply(int\|string $chatId, int $replyToMessageId, string $text, ?array $keyboard = null, ?array $extraParams = null)` | Shorthand for `sendMessage` with `reply_parameters` |
| `editMessageText(...)` | Edit a text or rich message. Requires **either** `$text` or `$richMessage` |
| `editMessageCaption(...)` | Edit a caption |
| `editMessageMedia(...)` | Replace animation/audio/document/photo/video; supports local `Media` uploads |
| `editMessageLiveLocation(...)` | Edit an ongoing live location |
| `editMessageReplyMarkup(...)` | Replace only the keyboard |
| `stopMessageLiveLocation(...)` | Halt a live location before its `live_period` expires |
| `deleteMessage(int\|string $chatId, int $messageId)` | Delete one message |
| `deleteMessages(int\|string $chatId, array $messageIds)` | Delete many messages in one call |
| `forwardMessage(...)` | Forward a single message |
| `forwardMessages(...)` | Forward multiple messages |
| `copyMessage(...)` | Copy a single message |
| `copyMessages(...)` | Copy multiple messages |
| `setMessageReaction(...)` | Set or replace reactions |
| `deleteMessageReaction(...)` | Remove a specific reaction |
| `deleteAllMessageReactions(...)` | Remove up to 10,000 recent reactions |
| `sendChatAction(...)` | `typing`, `upload_photo`, `record_video`, etc. |
| `sendDice(...)` | Animated dice/emoji throw |
| `sendPoll(...)` | Regular or quiz poll. Options may be plain strings or `InputPollOption` arrays (with media) |
| `stopPoll(...)` | Close a poll |
| `sendVenue(...)` | Venue location with title + address |
| `sendLocation(...)` | Static or live location |
| `sendContact(...)` | Share a phone contact |

**Examples:**

```php
// Reply markup + parse mode
$client->sendMessage(
    chatId: 123,
    text: '<b>Hello</b>',
    keyboard: (new KeyboardBuilder())->inlineRow(['OK' => 'cb_ok'])->build(),
    parseMode: 'HTML',
)->await();

// Reply to a message using reply_parameters
$client->reply(123, 99, 'pong')->await();

// Rich message edit
$client->editMessageText(
    chatId: 123,
    messageId: 45,
    richMessage: ['html' => '<b>updated</b>'],
)->await();
```

### Media

`SendsMedia` — see [Media Uploads](#media-uploads).

| Method | Notes |
| --- | --- |
| `sendPhoto` / `sendLivePhoto` | Supports `caption`, `parse_mode`, `caption_entities`, `show_caption_above_media`, `has_spoiler` |
| `sendPaidMedia` | `star_count` required; rejects empty/invalid media arrays |
| `sendVideo` | Supports `duration`, `width`, `height`, `thumbnail`, `cover`, `start_timestamp`, `supports_streaming` |
| `sendAudio` | Supports `duration`, `performer`, `title`, `thumbnail` |
| `sendDocument` | Supports `thumbnail`, `disable_content_type_detection` |
| `sendAnimation` | Supports `duration`, `width`, `height`, `has_spoiler`, `thumbnail` |
| `sendSticker` | Optional `emoji` |
| `sendVoice` | Optional `duration` |
| `sendVideoNote` | Optional `duration`, `length`, `thumbnail` |
| `sendMediaGroup` | Max 10 items; prefer `reply_parameters` over the legacy `reply_to_message_id` |

### Chat Management

`ManagesChats` — `getChat`, `getChatMemberCount`, `getChatMembersCount` *(deprecated alias)*, `pinChatMessage`, `unpinChatMessage`, `unpinAllChatMessages`, `setChatTitle`, `setChatDescription`, `setChatPhoto`, `deleteChatPhoto`, `leaveChat`, `setChatPermissions`, `exportChatInviteLink`, `createChatInviteLink`, `editChatInviteLink`, `createChatSubscriptionInviteLink`, `editChatSubscriptionInviteLink`, `revokeChatInviteLink`, `setChatStickerSet`, `deleteChatStickerSet`, `getUserPersonalChatMessages`, `setChatMenuButton`, `getChatMenuButton`.

```php
$client->setChatPhoto($chatId, new Media('/path/to/photo.jpg'))->await();
$client->pinChatMessage($chatId, 42, disableNotification: true)->await();
```

### Chat Members

`ManagesChatMembers` — `getChatMember`, `getChatAdministrators`, `banChatMember`, `unbanChatMember`, `restrictChatMember`, `promoteChatMember`, `setChatAdministratorCustomTitle`, `setChatMemberTag`, `banChatSenderChat`, `unbanChatSenderChat`, `approveChatJoinRequest`, `declineChatJoinRequest`, `answerChatJoinRequestQuery`, `sendChatJoinRequestWebApp`, `getUserChatBoosts`, `getUserProfilePhotos`.

```php
$client->banChatMember($chatId, $userId, untilDate: time() + 3600)->await();
$client->promoteChatMember($chatId, $userId, ['can_delete_messages' => true])->await();
```

### Stickers

`ManagesStickers` — `getStickerSet`, `uploadStickerFile`, `createNewStickerSet`, `addStickerToSet`, `deleteStickerFromSet`, `setStickerPositionInSet`, `replaceStickerInSet`, `setStickerSetThumbnail`, `setStickerSetTitle`, `deleteStickerSet`, `setCustomEmojiStickerSetThumbnail`, `setStickerEmojiList`, `setStickerKeywords`, `setStickerMaskPosition`, `getCustomEmojiStickers`.

```php
$client->uploadStickerFile($userId, new Media('/path/to/sticker.webp'), 'static')->await();

$client->createNewStickerSet(
    userId: $userId,
    name: 'my_pack_by_bot',
    title: 'My Pack',
    stickers: [['sticker' => 'attach://file', 'format' => 'static', 'emoji_list' => ['😀']]],
)->await();
```

### Forum Topics

`ManagesForumTopics` — `createForumTopic`, `editForumTopic`, `closeForumTopic`, `reopenForumTopic`, `deleteForumTopic`, `unpinAllForumTopicMessages`, `unpinAllGeneralForumTopicMessages`, `getForumTopicIconStickers`, `editGeneralForumTopic`, `closeGeneralForumTopic`, `reopenGeneralForumTopic`, `hideGeneralForumTopic`, `unhideGeneralForumTopic`.

```php
$client->createForumTopic($chatId, 'New Topic', iconColor: 0x6FB9F0)->await();
```

### Bot Profile

`ManagesBotProfile` — `getMe`, `setMyName`, `getMyName`, `setMyDescription`, `getMyDescription`, `setMyShortDescription`, `getMyShortDescription`, `setMyCommands`, `deleteMyCommands`, `getMyCommands`, `setMyDefaultAdministratorRights`, `getMyDefaultAdministratorRights`, `setMyProfilePhoto`, `removeMyProfilePhoto`, `setUserEmojiStatus`, `getUserProfileAudios`, `logOut`, `close`.

```php
$client->setMyCommands([
    ['command' => 'start', 'description' => 'Start the bot'],
])->await();

$client->setMyProfilePhoto(new Media('/path/to/avatar.jpg'))->await();
```

### Payments & Stars

`HandlesPayments` — `sendInvoice`, `createInvoiceLink`, `answerShippingQuery`, `answerPreCheckoutQuery`, `getMyStarBalance`, `getStarTransactions`, `refundStarPayment`, `editUserStarSubscription`.

For **Telegram Stars**, pass `currency: 'XTR'` and `providerToken: ''`. For subscriptions use `subscriptionPeriod: 2592000` (30 days).

```php
$client->sendInvoice(
    chatId: 123,
    title: 'Premium',
    description: 'Premium plan',
    payloadStr: 'order_123',
    providerToken: '',
    currency: 'XTR',
    prices: [['label' => 'Premium', 'amount' => 100]],
    subscriptionPeriod: 2592000,
)->await();
```

### Games

`HandlesGames` — `sendGame`, `setGameScore`, `getGameHighScores`.

Either provide both `chatId` and `messageId`, or `inlineMessageId`.

### Inline Queries

`HandlesInlineQueries` — `answerCallbackQuery`, `answerInlineQuery`, `answerWebAppQuery`, `answerGuestQuery`, `savePreparedInlineMessage`, `savePreparedKeyboardButton`.

```php
$client->answerInlineQuery(
    inlineQueryId: 'q1',
    results: [[
        'type' => 'article',
        'id' => '1',
        'title' => 'Sample',
        'input_message_content' => ['message_text' => 'Hello!'],
    ]],
    nextOffset: 'page-2',
    button: ['text' => 'Open', 'start_parameter' => 'go'],
)->await();
```

### Updates & Webhooks

`HandlesUpdates` — `handleUpdate(?string $secretToken = null): array`, `getUpdates(?int $offset, ?int $limit, ?int $timeout, ?array $allowedUpdates, ?array $extraParams): Future`.

`HandlesWebhooks` — `setWebhook`, `deleteWebhook`, `getWebhookInfo`.

```php
$client->setWebhook(
    url: 'https://example.com/webhook.php',
    certificate: new Media('/path/to/cert.pem'),
    maxConnections: 40,
    allowedUpdates: ['message', 'callback_query'],
    dropPendingUpdates: false,
    secretToken: 'secret',
    ipAddress: '1.2.3.4',
)->await();
```

### Stories

`HandlesStories` — `postStory`, `repostStory`, `editStory`, `deleteStory`.

```php
$client->postStory(
    businessConnectionId: 'bc_123',
    content: ['type' => 'photo', 'photo' => 'https://example.com/p.jpg'],
    activePeriod: 86400,
    caption: 'New drop',
)->await();
```

### Business Accounts

`HandlesBusiness` — `getBusinessConnection`, `readBusinessMessage`, `deleteBusinessMessages`, `setBusinessAccountName`, `setBusinessAccountUsername`, `setBusinessAccountBio`, `setBusinessAccountProfilePhoto`, `removeBusinessAccountProfilePhoto`, `setBusinessAccountGiftSettings`, `getBusinessAccountStarBalance`, `transferBusinessAccountStars`, `getBusinessAccountGifts`, `convertGiftToStars`, `upgradeGift`, `transferGift`, `sendChecklist`, `editMessageChecklist`.

```php
$client->setBusinessAccountProfilePhoto('bc_123', new Media('/path/to/avatar.mp4'))->await();
```

### Gifts

`HandlesGifts` — `getAvailableGifts`, `sendGift`, `giftPremiumSubscription`, `getUserGifts`, `getChatGifts`.

`sendGift` requires **exactly one** of `userId` or `chatId` — otherwise an `InvalidArgumentException` is thrown.

```php
$client->sendGift(userId: 42, chatId: null, giftId: 'gift-1', payForUpgrade: true)->await();
```

### Verification

`HandlesVerification` — `verifyUser`, `verifyChat`, `removeUserVerification`, `removeChatVerification`.

```php
$client->verifyUser(42, customDescription: 'Verified merchant')->await();
```

### Managed Bots

`ManagesManagedBots` — `getManagedBotToken`, `replaceManagedBotToken`, `getManagedBotAccessSettings`, `setManagedBotAccessSettings`.

```php
$client->setManagedBotAccessSettings(
    userId: 42,
    isAccessRestricted: true,
    addedUserIds: [100, 200],
)->await();
```

### Suggested Posts

`HandlesSuggestedPosts` — `approveSuggestedPost`, `declineSuggestedPost`.

```php
$client->approveSuggestedPost($chatId, $messageId, sendDate: time() + 60)->await();
$client->declineSuggestedPost($chatId, $messageId, comment: 'Not suitable')->await();
```

### Ephemeral Messages

`HandlesEphemeralMessages` — `editEphemeralMessageText`, `editEphemeralMessageMedia`, `editEphemeralMessageCaption`, `editEphemeralMessageReplyMarkup`, `deleteEphemeralMessage`.

```php
$client->editEphemeralMessageText(
    chatId: 123,
    receiverUserId: 42,
    ephemeralMessageId: 5,
    text: 'Updated',
)->await();
```

### Rich Messages

`HandlesRichMessages` — `sendRichMessage`, `sendRichMessageDraft`, `sendMessageDraft`.

`sendRichMessage` supports embedded `Media` objects — they are uploaded via multipart and rewritten to `attach://` references automatically.

```php
$client->sendRichMessage(
    chatId: 123,
    richMessage: [
        'html' => '<b>Hello</b>',
        'media' => [
            ['media' => ['type' => 'photo', 'photo' => new Media('/path/to/a.jpg')]],
        ],
    ],
)->await();
```

### Telegram Passport

`HandlesPassport` — `setPassportDataErrors`.

```php
$client->setPassportDataErrors($userId, [
    ['source' => 'data', 'type' => 'passport', 'field_name' => 'passport_number', 'message' => 'Invalid'],
])->await();
```

### Dynamic Method Calls

`Client::__call()` maps any unknown method to a Telegram API call with the first argument as the payload:

```php
// Equivalent to $client->request('getMyStarBalance', [])
$client->getMyStarBalance()->await();

// With parameters
$client->getSomeCustomMethod(['foo' => 'bar'])->await();
```

---

## TODO

- [ ] Add MTProto support
- [ ] Implement login with User Bot
- [ ] Add integration tests against a local Bot API server
- [ ] Document migration guide from v2.x

---

## License

MIT License — see the [LICENSE](LICENSE) file.