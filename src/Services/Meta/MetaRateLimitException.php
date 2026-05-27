<?php

declare(strict_types=1);

namespace MisterCo\Reports\Services\Meta;

final class MetaRateLimitException extends MetaApiException
{
    public function __construct(string $message, public readonly ?int $retryAfterSegundos = null)
    {
        parent::__construct($message, httpStatus: 429);
    }
}
