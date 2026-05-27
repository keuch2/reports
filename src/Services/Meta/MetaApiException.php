<?php

declare(strict_types=1);

namespace MisterCo\Reports\Services\Meta;

use RuntimeException;
use Throwable;

class MetaApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?int $metaCode = null,
        public readonly ?string $metaType = null,
        public readonly ?string $metaSubcode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function esTokenInvalido(): bool
    {
        // 190 = invalid OAuth token; 102 = session expired; 467 = invalid access token.
        return in_array($this->metaCode, [190, 102, 467], true);
    }
}
