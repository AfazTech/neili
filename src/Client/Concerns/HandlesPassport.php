<?php

/**
 * @author Abolfazl Majidi (Afaz)
 * @package neili
 * @license https://opensource.org/licenses/MIT
 * @link https://github.com/AfazTech/neili
 */

declare(strict_types=1);

namespace Neili\Client\Concerns;

use Amp\Future;

trait HandlesPassport
{
    /**
     * Inform a user that some of the Telegram Passport elements they provided
     * contain errors. The user will not be able to re-submit their Passport
     * until the errors are fixed.
     *
     * @param array $errors Array of PassportElementError payloads
     */
    public function setPassportDataErrors(int $userId, array $errors): Future
    {
        return $this->request('setPassportDataErrors', [
            'user_id' => $userId,
            'errors' => json_encode($errors),
        ]);
    }
}
