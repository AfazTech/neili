<?php

declare(strict_types=1);

namespace Neili\Client\Multipart;

use Amp\ByteStream\ReadableIterableStream;
use Amp\ByteStream\ReadableStream;
use Amp\Http\Client\HttpContent;
use function Amp\File\openFile;

/**
 * Streaming multipart/form-data body with a precomputed Content-Length.
 *
 * Two reasons this class exists:
 *
 *   1. Telegram's nginx rejects multipart bodies sent with
 *      Transfer-Encoding: chunked. Amp's built-in Form sends chunked
 *      because its length cannot be known ahead of time. Building the
 *      body manually lets us hand the HTTP client an exact
 *      Content-Length, which Telegram accepts.
 *
 *   2. File contents must not be loaded into memory. The generator
 *      below yields each part's header as one string, then streams
 *      file bytes from disk with Amp\File (~8 KiB per chunk), then
 *      yields the part's footer. The HTTP client pulls the stream and
 *      writes chunks to the socket as they become available, so the
 *      full body never sits in memory.
 */
final class MultipartBody implements HttpContent
{
    private int $contentLength;

    /**
     * @param array<int, array{
     *     header: string,
     *     value?: string,
     *     path?: string,
     *     size?: int,
     *     footer: string
     * }> $parts
     */
    public function __construct(
        private string $boundary,
        private array $parts,
        private string $closing,
    ) {
        $total = strlen($closing);

        foreach ($parts as $part) {
            $total += strlen($part['header']);
            $total += isset($part['size']) ? $part['size'] : strlen($part['value'] ?? '');
            $total += strlen($part['footer']);
        }

        $this->contentLength = $total;
    }

    public function getContent(): ReadableStream
    {
        return new ReadableIterableStream($this->generate());
    }

    public function getContentLength(): ?int
    {
        return $this->contentLength;
    }

    public function getContentType(): ?string
    {
        return 'multipart/form-data; boundary=' . $this->boundary;
    }

    /**
     * @return \Generator<int, string>
     */
    private function generate(): \Generator
    {
        foreach ($this->parts as $part) {
            yield $part['header'];

            if (isset($part['path'])) {
                $handle = openFile($part['path'], 'r');
                try {
                    while (null !== $chunk = $handle->read()) {
                        yield $chunk;
                    }
                } finally {
                    $handle->close();
                }
            } else {
                yield $part['value'] ?? '';
            }

            yield $part['footer'];
        }

        yield $this->closing;
    }
}
