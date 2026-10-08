<?php

declare(strict_types=1);

namespace Neili\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards the architectural invariant that Neili ships with zero internal
 * logging / observability dependencies: no PSR-3, no Monolog, no
 * error_log(), and no Neili\Logger class.
 *
 * The scan only looks for tokens that could only appear as real code
 * (or real imports) rather than as prose in comments. The guard file
 * itself is excluded because it legitimately contains those tokens
 * as string literals.
 */
final class NoLoggerReferenceTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const FORBIDDEN_TOKENS = [
        'LoggerInterface',
        'Psr\\Log',
        'Monolog',
        'Neili\\Logger',
        'error_log(',
        'getLogger(',
    ];

    public function testNoLoggerReferencesRemainInSourceOrTests(): void
    {
        $root = \dirname(__DIR__, 2);
        $scanPaths = [
            $root . '/src',
            $root . '/tests',
        ];
        $selfPath = (string) realpath(__FILE__);

        foreach ($scanPaths as $path) {
            self::assertDirectoryExists($path);

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $filePath = (string) realpath($file->getPathname());
                if ($filePath === $selfPath) {
                    // The guard itself legitimately contains the tokens
                    // as string literals; skip it.
                    continue;
                }

                $content = (string) file_get_contents($filePath);
                foreach (self::FORBIDDEN_TOKENS as $needle) {
                    self::assertStringNotContainsString(
                        $needle,
                        $content,
                        "Forbidden logger reference '{$needle}' found in {$filePath}"
                    );
                }
            }
        }
    }

    public function testComposerJsonDoesNotRequireAnyLoggerPackage(): void
    {
        $root = \dirname(__DIR__, 2);
        $composerPath = $root . '/composer.json';
        self::assertFileExists($composerPath);

        $content = (string) file_get_contents($composerPath);

        self::assertStringNotContainsString(
            'psr/log',
            $content,
            'composer.json must not require psr/log after logger removal'
        );
        self::assertStringNotContainsString(
            'monolog/',
            $content,
            'composer.json must not require any monolog package'
        );
    }
}
