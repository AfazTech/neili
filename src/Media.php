<?php

declare(strict_types=1);

namespace Neili;

class Media
{
    /** Absolute local path; existence is checked asynchronously during upload. */
    public string $filePath;

    /** Capture relative paths now without performing filesystem metadata I/O. */
    public function __construct(string $filePath)
    {
        if ($filePath === '' || str_contains($filePath, "\0")) {
            throw new \InvalidArgumentException('Media path must be non-empty and contain no null bytes');
        }

        $absolute = str_starts_with($filePath, '/')
            || (DIRECTORY_SEPARATOR === '\\' && (str_starts_with($filePath, '\\')
                || preg_match('/^[A-Za-z]:[\\\\\/]/', $filePath) === 1));
        if (!$absolute) {
            $directory = getcwd();
            if ($directory === false) {
                throw new \RuntimeException('Cannot resolve current working directory');
            }
            $filePath = $directory . DIRECTORY_SEPARATOR . $filePath;
        }
        // Preserve symlinks and dot segments; the filesystem resolves them on open.
        $this->filePath = $filePath;
    }
}
