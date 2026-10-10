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
use Amp\Future;
use Amp\Http\Client\Request;
use function Amp\async;

trait ManagesFiles
{
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
        return "https://api.telegram.org/file/bot"
            . $this->settings->getAccessToken()
            . "/" . ltrim($filePath, '/');
    }

    /**
     * Download file from Telegram servers.
     *
     * Runs inside an Amp Fiber (via async()). Every awaitable operation
     * uses ->await() instead of yield, because this codebase targets
     * amphp v3 where yield is not the suspension primitive. Using yield
     * inside the closure would turn it into a Generator that async()
     * never executes, leaving the destination file unwritten.
     *
     * The response body is streamed chunk by chunk so large files do
     * not have to fit in memory.
     *
     * Returns the destination path on success.
     */
    public function downloadFile(string $fileId, string $destinationPath): Future
    {
        return async(function () use ($fileId, $destinationPath): string {
            $fileInfo = $this->getFile($fileId)->await();

            if (!isset($fileInfo['result']['file_path'])) {
                throw new \RuntimeException("Invalid file_id or file not found");
            }

            $request = new Request($this->getFileUrl($fileInfo['result']['file_path']));
            $request->setTransferTimeout((float) $this->settings->getTimeout());
            $request->setTcpConnectTimeout((float) $this->settings->getConnectionTimeout());

            $response = $this->httpClient->request($request);

            if ($response->getStatus() >= 300) {
                throw new \RuntimeException(
                    "Failed to download file: HTTP " . $response->getStatus()
                );
            }

            $handle = File\openFile($destinationPath, 'w');

            try {
                foreach ($response->getBody() as $chunk) {
                    $handle->write($chunk);
                }
            } finally {
                $handle->close();
            }

            return $destinationPath;
        });
    }
}
