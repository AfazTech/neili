<?php

/**
 * @author Abolfazl Majidi (Afaz)
 * @package neili
 * @license https://opensource.org/licenses/MIT
 * @link https://github.com/AfazTech/neili
 */

declare(strict_types=1);

namespace Neili\Client\Concerns;

use Amp\File;
use Amp\Http\Client\Request;
use Amp\Future;
use function Amp\async;

trait ManagesFiles
{
    /**
     * Chunk size used while streaming remote files to disk.
     * 64 KiB balances syscall overhead against per-chunk memory pressure.
     */
    private const DOWNLOAD_CHUNK_SIZE = 65536;

    /**
     * Get file info from Telegram server.
     */
    public function getFile(string $fileId): Future
    {
        return $this->request('getFile', ['file_id' => $fileId]);
    }

    /**
     * Get the public download URL of a file given its remote file_path.
     */
    public function getFileUrl(string $filePath): string
    {
        return "https://api.telegram.org/file/bot" . $this->settings->getAccessToken() . "/" . $filePath;
    }

    /**
     * Download file from Telegram servers.
     *
     * The response body is streamed to disk in chunks instead of being
     * buffered in memory, so the method is safe for large files (e.g. when
     * the bot runs against a local Bot API server with the 2 GB limit).
     *
     * Returns the destination path on success.
     */
    public function downloadFile(string $fileId, string $destinationPath): Future
    {
        return async(function () use ($fileId, $destinationPath) {
            $fileInfo = yield $this->getFile($fileId);
            if (!isset($fileInfo['result']['file_path'])) {
                throw new \RuntimeException("Invalid file_id or file not found");
            }

            $remotePath = $fileInfo['result']['file_path'];
            $url = "https://api.telegram.org/file/bot" . $this->settings->getAccessToken() . "/" . $remotePath;

            $request = new Request($url);
            $request->setTransferTimeout((float) $this->settings->getTimeout());
            $request->setTcpConnectTimeout((float) $this->settings->getConnectionTimeout());

            $response = yield $this->httpClient->request($request);

            if ($response->getStatus() >= 400) {
                throw new \RuntimeException(
                    "Failed to download file: HTTP " . $response->getStatus()
                );
            }

            $handle = yield File\openFile($destinationPath, 'w');

            try {
                $body = $response->getBody();

                while (null !== $chunk = yield $body->read(self::DOWNLOAD_CHUNK_SIZE)) {
                    yield $handle->write($chunk);
                }
            } finally {
                yield $handle->close();
            }

            return $destinationPath;
        });
    }
}
