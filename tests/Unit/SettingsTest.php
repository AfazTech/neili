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
        self::assertNull($settings->getPollerMaxConcurrency());
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
}
