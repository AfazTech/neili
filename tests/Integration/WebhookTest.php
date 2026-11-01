<?php

declare(strict_types=1);

namespace Neili\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebhookTest extends TestCase
{
    private static $server;
    private static string $router;
    private static string $log;
    private static string $url;

    public static function setUpBeforeClass(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if ($socket === false) {
            throw new \RuntimeException('Cannot reserve local test port');
        }
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        self::$url = 'http://' . $address;
        self::$router = tempnam(sys_get_temp_dir(), 'neili_webhook_');
        self::$log = tempnam(sys_get_temp_dir(), 'neili_server_');
        $autoload = var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true);
        file_put_contents(self::$router, '<?php require ' . $autoload . ';' . <<<'ROUTER'
$client = new \Neili\Client((new \Neili\Settings())->setAccessToken('test'));
try {
    $update = $client->handleUpdate('test-secret');
    header('Content-Type: application/json');
    echo json_encode($update);
} catch (Throwable $error) {
    echo $error->getMessage();
}
ROUTER);
        self::$server = proc_open([PHP_BINARY, '-S', $address, self::$router],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', self::$log, 'a'], 2 => ['file', self::$log, 'a']], $pipes);
        if (!is_resource(self::$server)) {
            throw new \RuntimeException('Cannot start webhook test server');
        }
        $deadline = microtime(true) + 3;
        do {
            $ready = @stream_socket_client('tcp://' . $address, $errno, $error, 0.05);
            if ($ready !== false) {
                fclose($ready);
                return;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        self::tearDownAfterClass();
        throw new \RuntimeException('Webhook test server did not start');
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
        @unlink(self::$router);
        @unlink(self::$log);
    }

    public static function requests(): iterable
    {
        yield 'malformed JSON' => ['POST', 'test-secret', 'invalid', 400];
        yield 'scalar JSON' => ['POST', 'test-secret', 'null', 400];
        yield 'missing update id' => ['POST', 'test-secret', '{}', 400];
        yield 'invalid id' => ['POST', 'test-secret', '{"update_id":"1"}', 400];
        yield 'invalid secret' => ['POST', 'wrong', '{"update_id":1}', 403];
        yield 'missing secret' => ['POST', null, '{"update_id":1}', 403];
        yield 'unsupported method' => ['GET', 'test-secret', '', 405];
        yield 'valid update' => ['POST', 'test-secret', '{"update_id":1,"message":{"text":"hello"}}', 200];
    }

    #[DataProvider('requests')]
    public function testWebhookResponseStatus(string $method, ?string $secret, string $body, int $expected): void
    {
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => "Content-Type: application/json\r\n" . ($secret !== null ? "X-Telegram-Bot-Api-Secret-Token: {$secret}\r\n" : ''),
            'content' => $body,
            'ignore_errors' => true,
            'timeout' => 2,
        ]]);
        $response = file_get_contents(self::$url, false, $context);
        $headers = function_exists('http_get_last_response_headers')
            ? http_get_last_response_headers()
            : $http_response_header;
        self::assertStringContainsString(' ' . $expected . ' ', $headers[0]);
        if ($expected === 200) {
            self::assertSame(json_decode($body, true), json_decode($response, true));
        }
    }
}
