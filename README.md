# Neili

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Version](https://img.shields.io/github/v/tag/AfazTech/Neili?label=version&color=orange)](https://github.com/AfazTech/Neili/tags)

**Neili** is an async-first PHP library for building Telegram bots with Amp. It provides asynchronous API methods, long polling, webhook handling, and helpers for keyboards and media.

This repository includes the library source, configuration classes, tests, and practical examples to help you build bots for short-lived webhook requests or long-running CLI workers.

## Features

- Non-blocking HTTP requests powered by Amp
- Telegram Bot API method wrappers and dynamic API calls
- Long polling with a configurable default limit of 100 in-flight updates and exponential backoff
- Standard, multi-process, and fast-ack webhook modes
- `flush()` to wait for pending requests without awaiting each call
- Fluent reply and inline keyboard builder
- Local media uploads and streaming file downloads
- Configurable timeouts, API endpoint, and HTTP client
- Typed API exceptions and poller error callbacks
- Detailed documentation and usage examples

## Requirements

- PHP >= 8.1
- Composer
- PHP extensions required by the dependency graph; Composer checks these during installation
- `exec()`, `proc_open()`, and a PHP CLI binary for multi-process webhook mode
- PHP-FPM, LiteSpeed, or FrankenPHP for response detachment in fast-ack mode

Composer installs `amphp/file` ^4 and `amphp/http-client` ^5.3, including their Amp dependencies.

## Installation

Install the library in your application:

```bash
composer require afaztech/neili
```

To work on the library itself:

```bash
git clone https://github.com/AfazTech/Neili.git
cd Neili
composer install
```

## Configuration

Configure the client using `Neili\Settings`. The example below reads the bot token from an environment variable:

```php
use Neili\Client;
use Neili\Settings;

$token = getenv('TELEGRAM_BOT_TOKEN');
if ($token === false || $token === '') {
    throw new RuntimeException('Set TELEGRAM_BOT_TOKEN before starting the bot.');
}

$settings = (new Settings())
    ->setAccessToken($token)
    ->setTimeout(15, 5);

$client = new Client($settings);
```

Environment variables must be provided by your shell or hosting environment. Neili does not load `.env` files automatically.

## Usage

Choose either long polling or a webhook for each bot. Telegram does not allow `getUpdates` while a webhook is configured; call `$client->deleteWebhook()->await()` before switching back to long polling.

### Long Polling

Save this long-polling example as `bot.php`:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Neili\Client;
use Neili\Poller;
use Neili\Settings;

$token = getenv('TELEGRAM_BOT_TOKEN');
if ($token === false || $token === '') {
    throw new RuntimeException('Set TELEGRAM_BOT_TOKEN before starting the bot.');
}

$client = new Client((new Settings())->setAccessToken($token));
$poller = new Poller($client);

$poller->onError(function (Throwable $error, array $update, string $type): void {
    error_log("[{$type}] {$error->getMessage()}");
});

$poller->onMessage(function (array $update) use ($client): void {
    if (isset($update['message']['chat']['id'], $update['message']['text'])) {
        $client->sendMessage(
            $update['message']['chat']['id'],
            'Echo: ' . $update['message']['text'],
        )->await();
    }
});

$poller->start(discardOldUpdates: false);
```

Start the bot after setting `TELEGRAM_BOT_TOKEN` in your environment:

```bash
php bot.php
```

### Standard Webhook

Save this example as `webhook.php` and serve it at a public HTTPS endpoint. Set `TELEGRAM_BOT_TOKEN` and `TELEGRAM_WEBHOOK_SECRET` in the web server's PHP environment. Use the same secret when registering the webhook.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Neili\Client;
use Neili\Settings;

$token = getenv('TELEGRAM_BOT_TOKEN');
$secret = getenv('TELEGRAM_WEBHOOK_SECRET');
if ($token === false || $token === '' || $secret === false || $secret === '') {
    http_response_code(500);
    throw new RuntimeException('Set TELEGRAM_BOT_TOKEN and TELEGRAM_WEBHOOK_SECRET.');
}

$client = new Client((new Settings())->setAccessToken($token));
$update = $client->handleUpdate($secret);

if (isset($update['message']['chat']['id'], $update['message']['text'])) {
    $client->sendMessage(
        $update['message']['chat']['id'],
        'Echo: ' . $update['message']['text'],
    );
}

// Wait for pending API requests before completing the webhook response.
$client->flush();
```

Register the webhook once from a separate CLI setup script, using the client from the [Configuration](#configuration) example:

```php
$secret = getenv('TELEGRAM_WEBHOOK_SECRET');
if ($secret === false || $secret === '') {
    throw new RuntimeException('Set TELEGRAM_WEBHOOK_SECRET before registering the webhook.');
}

$client->setWebhook(
    url: 'https://example.com/webhook.php',
    allowedUpdates: ['message'],
    secretToken: $secret,
)->await();
```

Replace the URL with your endpoint. Webhook registration belongs in the setup script, not in `webhook.php`. The handler checks the secret header, rejects malformed updates, and returns the update for your application to process.

### Multi-Process Webhook

To process each update in a separate PHP CLI worker, replace the client settings in the webhook example with:

```php
$settings = (new Settings())
    ->setAccessToken($token)
    ->setMultiProcess(true)
    ->setPhpBinary('/usr/bin/php')
    ->setMultiProcessMaxWorkers(20);

$client = new Client($settings);
```

Use the actual PHP CLI path on your server. The parent launches a worker and replies without waiting for update processing. Each worker re-enters the same script; `handleUpdate()` reads the payload and the code below it processes the update. Make the token and secret available in both the web and CLI environments.

The worker limit defaults to 100 and applies per script and bot token on one host. When capacity is full, the endpoint returns `503` with `Retry-After: 1` so Telegram can retry. Slots are released when workers exit. There is no startup handshake, durable queue, or automatic recovery of failed application processing.

Use `->await()` when you need a result or dependent ordering, and call `$client->flush()` before the processing script ends for independent requests. See the [delivery modes](DOCUMENT.md#delivery-modes), including fast-ack, and the [flush reference](DOCUMENT.md#waiting-for-pending-operations).

## Documentation

For detailed documentation, see [DOCUMENT.md](DOCUMENT.md).

## Contributing

Contributions are welcome. Open an issue for a bug or proposal, and submit pull requests with focused changes and relevant tests. Run the test suite before submitting:

```bash
composer test
```

## License

This project is licensed under the [MIT License](LICENSE).
