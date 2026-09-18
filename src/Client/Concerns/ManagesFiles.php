<?php

/**
 * @version 2.2.11
 * @author Abolfazl Majidi (Afaz)
 * @package neili
 * @license https://opensource.org/licenses/MIT
 * @link https://github.com/AfazTech/neili
 */

declare(strict_types=1);

namespace Neili\Client\Concerns;

use Amp\Http\Client\Request;
use Amp\Future;
use function Amp\async;

trait ManagesFiles
{
    /**
     * Get file info from Telegram server
     */
    public function getFile(string $fileId): Future
    {
        return $this->request('getFile', ['file_id' => $fileId]);
    }

    /**
     * Get file URL for download
     */
    public function getFileUrl(string $fileId): string
    {
        return "https://api.telegram.org/file/bot" . $this->settings->getAccessToken() . "/" . $fileId;
    }

    /**
     * Download file from Telegram servers
     */
    public function downloadFile(string $fileId, string $destinationPath): Future
    {
        return async(function () use ($fileId, $destinationPath) {
            $fileInfo = yield $this->getFile($fileId);
            if (!isset($fileInfo['result']['file_path']))
                throw new \RuntimeException("Invalid file_id or file not found");
            $filePath = $fileInfo['result']['file_path'];
            $url = "https://api.telegram.org/file/bot" . $this->settings->getAccessToken() . "/" . $filePath;

            $request = new Request($url);
            $request->setTransferTimeout((float) $this->settings->getTimeout());
            $request->setTcpConnectTimeout((float) $this->settings->getConnectionTimeout());
            $response = yield $this->httpClient->request($request);
            $body = yield $response->getBody()->buffer();

            file_put_contents($destinationPath, $body);
            return $destinationPath;
        });
    }
}
