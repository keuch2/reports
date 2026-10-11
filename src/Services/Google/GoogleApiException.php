<?php

declare(strict_types=1);

namespace MisterCo\Reports\Services\Google;

use RuntimeException;
use Throwable;

class GoogleApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        /** Status textual de Google (UNAUTHENTICATED, PERMISSION_DENIED, invalid_grant…). */
        public readonly ?string $googleStatus = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /** El acceso fue revocado o venció: hay que reconectar la cuenta de Google. */
    public function esTokenInvalido(): bool
    {
        return $this->httpStatus === 401
            || in_array($this->googleStatus, ['UNAUTHENTICATED', 'invalid_grant'], true);
    }
}
