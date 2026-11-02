<?php

declare(strict_types=1);

namespace Neili\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class MultiProcessWebhookTest extends TestCase
{
    public function testWorkerCapacityAndRecovery(): void
    {
        if (!function_exists('exec') || !function_exists('proc_open')) {
            self::markTestSkipped('Process execution is unavailable');
        }
        $directory = sys_get_temp_dir() . '/neili_multi_' . bin2hex(random_bytes(8));
        mkdir($directory);
        $router = $directory . '/router.php';
        $autoload = var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true);
        $dir = var_export($directory, true);
        file_put_contents($router, '<?php require ' . $autoload . '; $directory = ' . $dir . ';' . <<<'ROUTER'
$settings = (new \Neili\Settings())->setAccessToken('test')->setMultiProcess(true)->setMultiProcessMaxWorkers(1)
    ->setPhpBinary(PHP_BINARY);
try {
    $update = (new \Neili\Client($settings))->handleUpdate('test-secret');
    file_put_contents($directory . '/started-' . $update['update_id'], 'yes');
    $deadline = microtime(true) + 10;
    while (!is_file($directory . '/release') && microtime(true) < $deadline) {
        usleep(10000);
    }
} catch (Throwable $error) {
    if (PHP_SAPI !== 'cli') echo $error->getMessage();
}
ROUTER);
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertIsResource($socket);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $process = proc_open([PHP_BINARY, '-S', $address, $router],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', $directory . '/server.log', 'a'],
                2 => ['file', $directory . '/server.log', 'a']], $pipes);
        self::assertIsResource($process);
        try {
            $this->waitFor(function () use ($address): bool {
                $socket = @stream_socket_client('tcp://' . $address, $errno, $error, 0.05);
                if ($socket === false) return false;
                fclose($socket);
                return true;
            });
            self::assertSame(200, $this->request($address . '/', 2));
            $this->waitFor(fn () => is_file($directory . '/started-2'));
            self::assertSame(503, $this->request($address . '/', 3));
            self::assertFileDoesNotExist($directory . '/started-3');
            touch($directory . '/release');
            $this->waitFor(fn () => $this->request($address . '/', 4) === 200);
            $this->waitFor(fn () => is_file($directory . '/started-4'));
        } finally {
            touch($directory . '/release');
            proc_terminate($process);
            proc_close($process);
            foreach (glob($directory . '/*') as $file) @unlink($file);
            @rmdir($directory);
        }
    }

    private function request(string $address, int $id): int
    {
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nX-Telegram-Bot-Api-Secret-Token: test-secret",
            'content' => json_encode(['update_id' => $id]),
            'ignore_errors' => true,
            'timeout' => 7,
        ]]);
        file_get_contents('http://' . $address, false, $context);
        $headers = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : $http_response_header;
        return (int) explode(' ', $headers[0])[1];
    }

    private function waitFor(callable $ready): void
    {
        $deadline = microtime(true) + 3;
        do {
            if ($ready()) return;
            usleep(10000);
        } while (microtime(true) < $deadline);
        self::fail('Timed out waiting for worker/server readiness');
    }
}
