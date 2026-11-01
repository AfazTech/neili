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
- `exec()` and a PHP CLI binary for multi-process webhook mode
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

For webhook handlers, schedule independent requests and call `$client->flush()` before the script ends. Use `->await()` when you need a result or dependent ordering. See the [webhook examples](DOCUMENT.md#standard-webhook) and [flush reference](DOCUMENT.md#waiting-for-pending-operations).

## Documentation

For detailed documentation, see [DOCUMENT.md](DOCUMENT.md).

## Contributing

Contributions are welcome. Open an issue for a bug or proposal, and submit pull requests with focused changes and relevant tests. Run the test suite before submitting:

```bash
composer test
```

## License

This project is licensed under the [MIT License](LICENSE).
