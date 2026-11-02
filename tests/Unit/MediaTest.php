<?php

declare(strict_types=1);

namespace Neili\Tests\Unit;

use Neili\Media;
use PHPUnit\Framework\TestCase;

final class MediaTest extends TestCase
{
    public function testConstructorDefersFilesystemValidation(): void
    {
        $media = new Media('/path/to/definitely/not/existing/file.bin');
        self::assertSame('/path/to/definitely/not/existing/file.bin', $media->filePath);
    }

    public function testRelativePathIsAnchoredAtConstruction(): void
    {
        $directory = getcwd();
        $media = new Media('future-photo.jpg');
        try {
            chdir(sys_get_temp_dir());
            self::assertSame($directory . DIRECTORY_SEPARATOR . 'future-photo.jpg', $media->filePath);
        } finally {
            chdir($directory);
        }
    }

    public function testNullBytePathIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Media("photo\0.jpg");
    }

    public function testEmptyPathIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Media('');
    }
}
