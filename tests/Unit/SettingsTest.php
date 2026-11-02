<?php

declare(strict_types=1);

namespace Neili\Tests\Unit;

use Neili\Settings;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase
{
    public function testDefaults(): void
    {
        $settings = new Settings();

        self::assertSame('https://api.telegram.org/bot', $settings->getApiUrl());
        self::assertTrue($settings->isApiVerifySSL());
        self::assertSame(10, $settings->getTimeout());
        self::assertSame(5, $settings->getConnectionTimeout());
        self::assertFalse($settings->isMultiProcess());
        self::assertSame('/usr/bin/php', $settings->getPhpBinary());
        self::assertSame(3, $settings->getPollerTimeout());
        self::assertSame(1, $settings->getPollerBackoffBase());
        self::assertSame(32, $settings->getPollerMaxBackoff());
        self::assertSame(100, $settings->getPollerMaxConcurrency());
        self::assertNull($settings->getHttpClient());
    }

    public function testFluentSetters(): void
    {
        $settings = (new Settings())
            ->setAccessToken('123:abc')
            ->setApiUrl('https://example.com/bot')
            ->setApiVerifySSL(false)
            ->setTimeout(20, 8)
            ->setPollerTimeout(5)
            ->setPollerBackoffBase(2)
            ->setPollerMaxBackoff(60)
            ->setPollerMaxConcurrency(50)
            ->setPhpBinary('/usr/local/bin/php');

        self::assertSame('123:abc', $settings->getAccessToken());
        self::assertSame('https://example.com/bot', $settings->getApiUrl());
        self::assertFalse($settings->isApiVerifySSL());
        self::assertSame(20, $settings->getTimeout());
        self::assertSame(8, $settings->getConnectionTimeout());
        self::assertSame(5, $settings->getPollerTimeout());
        self::assertSame(2, $settings->getPollerBackoffBase());
        self::assertSame(60, $settings->getPollerMaxBackoff());
        self::assertSame(50, $settings->getPollerMaxConcurrency());
        self::assertSame('/usr/local/bin/php', $settings->getPhpBinary());
    }

    public function testConcurrencyCanBeExplicitlyUnlimited(): void
    {
        self::assertNull((new Settings())->setPollerMaxConcurrency(null)->getPollerMaxConcurrency());
    }

    public function testConcurrencyRejectsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Settings())->setPollerMaxConcurrency(0);
    }

    public function testConcurrencyRejectsNegativeValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Settings())->setPollerMaxConcurrency(-1);
    }

    public function testSetTimeoutKeepsConnectionTimeoutWhenOmitted(): void
    {
        $settings = (new Settings())->setTimeout(30);
        self::assertSame(30, $settings->getTimeout());
        self::assertSame(5, $settings->getConnectionTimeout());
    }

    public function testSetMultiProcessThrowsWhenExecMissing(): void
    {
        if (function_exists('exec')) {
            self::markTestSkipped('exec() is enabled on this environment');
        }

        $this->expectException(\RuntimeException::class);

        (new Settings())->setMultiProcess(true);
    }

    public function testSetMultiProcessFlipsFlag(): void
    {
        if (!function_exists('exec')) {
            self::markTestSkipped('exec() is disabled on this environment');
        }

        $settings = (new Settings())->setMultiProcess(true);
        self::assertTrue($settings->isMultiProcess());
    }

    public function testMultiProcessWorkerLimit(): void
    {
        $settings = new Settings();
        self::assertSame(100, $settings->getMultiProcessMaxWorkers());
        self::assertSame($settings, $settings->setMultiProcessMaxWorkers(4));
        self::assertSame(4, $settings->getMultiProcessMaxWorkers());
        $this->expectException(\InvalidArgumentException::class);
        $settings->setMultiProcessMaxWorkers(0);
    }

    public function testSettingsDoesNotExposeLoggerApi(): void
    {
        $reflection = new \ReflectionClass(Settings::class);

        self::assertFalse(
            method_exists(Settings::class, 'getLogger'),
            'Settings must not expose a get-logger method after logger removal'
        );

        self::assertFalse(
            $reflection->hasProperty('logger'),
            'Settings must not declare a logger property after logger removal'
        );

        $constructor = $reflection->getConstructor();
        if ($constructor !== null) {
            self::assertSame(
                0,
                $constructor->getNumberOfParameters(),
                'Settings constructor must not accept any parameter after logger removal'
            );
            self::assertSame(
                0,
                $constructor->getNumberOfRequiredParameters(),
                'Settings constructor must not require any parameter after logger removal'
            );
        }
    }
    public function testFastAckCannotBeEnabledAfterMultiProcess(): void
    {
        $settings = (new Settings())->setMultiProcess(true);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot enable fast-ack');
        $settings->setFastAck(true);
    }

    public function testMultiProcessCannotBeEnabledAfterFastAck(): void
    {
        $settings = (new Settings())->setFastAck(true);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot enable multi-process');
        $settings->setMultiProcess(true);
    }

    public function testDeliveryModesCanBeSwitchedAfterDisablingPreviousMode(): void
    {
        $settings = (new Settings())->setFastAck(true)->setFastAck(false)->setMultiProcess(true);
        self::assertTrue($settings->isMultiProcess());
        self::assertFalse($settings->isFastAck());
        $settings->setMultiProcess(false)->setFastAck(true);
        self::assertFalse($settings->isMultiProcess());
        self::assertTrue($settings->isFastAck());
    }

    public function testMultiProcessRejectsDisabledProcOpen(): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('Subprocess execution is unavailable');
        }
        $autoload = var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true);
        $code = 'require ' . $autoload . '; try { (new \\Neili\\Settings())->setMultiProcess(true); exit(1); }'
            . ' catch (\\RuntimeException $e) { echo $e->getMessage(); }';
        $process = proc_open([PHP_BINARY, '-d', 'disable_functions=proc_open', '-r', $code],
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $errors);
        self::assertSame('Cannot enable multi-process: proc_open disabled', $output);
    }

}
