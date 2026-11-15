<?php

namespace App\Exceptions\Kolesa;

use Throwable;

class KolesaApiException extends KolesaException
{
    public function __construct(
        string $message = '',
        public readonly ?int $statusCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
