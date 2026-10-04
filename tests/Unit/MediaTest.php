<?php

declare(strict_types=1);

namespace Neili\Tests\Unit;

use Neili\Media;
use PHPUnit\Framework\TestCase;

final class MediaTest extends TestCase
{
    public function testConstructorResolvesRealPath(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'neili_media_');
        file_put_contents($file, 'binary');

        try {
            $media = new Media($file);
            self::assertSame(realpath($file), $media->filePath);
        } finally {
            @unlink($file);
        }
    }

    public function testConstructorThrowsOnMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('File not found');

        new Media('/path/to/definitely/not/existing/file.bin');
    }
}
