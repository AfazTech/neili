# Neili

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Amp](https://img.shields.io/badge/built%20on-Amp-orange.svg)](https://amphp.org/)

Async-first PHP library for building Telegram bots on top of [Amp](https://amphp.org/).

## Features

- Non-blocking HTTP client built on `amphp/http-client`
- Wrappers for the Telegram Bot API methods
- Long-polling `Poller` with exponential backoff + jitter and concurrency control
- Webhook handler with three delivery modes:
  - **Standard webhook** — the script replies to Telegram when it finishes
  - **Multi-process** — each update is forked into a PHP CLI worker via `exec()`
  - **Fast-ack** — replies `200 OK` immediately and keeps processing (requires FPM / LiteSpeed / FrankenPHP)
- Fluent `KeyboardBuilder` for reply and inline keyboards
- `Media` helper for local file uploads with automatic `attach://` mapping
- Streaming file downloads via `amphp/file` (safe for large files)
- Full exception taxonomy: `TransientException`, `PermanentException`, `RateLimitException`
- Zero internal logging — errors surface through exceptions and `Poller::onError()`

## Requirements

- PHP >= 8.1
- `amphp/amp` ^3
- `amphp/file` ^3.2
- `amphp/http-client` ^5.3
- PHP extensions: `fileinfo`; `posix` (optional, recommended)
- `exec()` — required only for multi-process webhook mode

## Installation

```bash
composer require afaztech/neili
```

## Usage

### Long polling

```php
use Neili\Client;
use Neili\Poller;
use Neili\Settings;

$settings = (new Settings())->setAccessToken('YOUR_BOT_TOKEN');
$client   = new Client($settings);
$poller   = new Poller($client);

$poller->onMessage(function (array $update) use ($client) {
    $chatId = $update['message']['chat']['id'] ?? null;
    $text   = $update['message']['text'] ?? null;
    if ($chatId && $text) {
        $client->sendMessage($chatId, "Echo: {$text}");
    }
});

$poller->start();
```

### Standard webhook

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

### Multi-process webhook

Each update is forked into a separate PHP CLI worker and the parent returns to Telegram immediately.

```php
$settings = (new Settings())
    ->setAccessToken('YOUR_BOT_TOKEN')
    ->setMultiProcess(true)
    ->setPhpBinary('/usr/bin/php');

$client = new Client($settings);
$client->handleUpdate('YOUR_SECRET_TOKEN');
```

### Fast-ack webhook

Replies `200 OK` to Telegram as soon as possible and keeps processing in the same request. Mutually exclusive with multi-process mode — enabling both throws `RuntimeException`.

```php
$settings = (new Settings())
    ->setAccessToken('YOUR_BOT_TOKEN')
    ->setFastAck(true);

$client = new Client($settings);

$update = $client->handleUpdate('YOUR_SECRET_TOKEN');

// Telegram has already received its 200 OK. Slow work continues below.
```

Detaches the response only on PHP-FPM, LiteSpeed, or FrankenPHP. On other SAPIs it falls back to flushing the output buffer and behaves like a standard webhook.

## Documentation

For detailed documentation, see [DOCUMENT.md](DOCUMENT.md).

## Contributing

Contributions are welcome via pull requests. Please keep the codebase stable, tested, and backward-compatible.

## License

This project is licensed under the [MIT License](LICENSE).