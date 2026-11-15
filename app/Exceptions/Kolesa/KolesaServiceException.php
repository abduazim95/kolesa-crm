<?php

namespace App\Exceptions\Kolesa;

use Throwable;

/**
 * Represents a business-level error from Kolesa's setService endpoint.
 * HTTP response is always 200 — the real status lives in the JSON body
 * as `{"error_code": <int>, "error": "<message>"}`.
 */
class KolesaServiceException extends KolesaException
{
    public function __construct(
        string $message,
        public readonly int $errorCode,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromResponse(array $body): self
    {
        $code = (int) ($body['error_code'] ?? 0);
        $message = (string) ($body['error'] ?? 'Unknown Kolesa error');

        return new self($message, $code);
    }

    /**
     * Map Kolesa error_code to our internal error_type bucket.
     * Codes sourced from kolesa-api src/Application/Controllers/User.php::setServiceSingle.
     */
    public function errorType(): string
    {
        return match ($this->errorCode) {
            101 => 'insufficient_funds',
            178 => 'duplicate_operation',
            158 => 'category_disabled',
            165, 166, 187 => 'package_unavailable',
            174 => 'blacklisted',
            103 => 'invalid_storage',
            108 => 'invalid_service',
            113 => 'report_not_relevant',
            default => 'api_error',
        };
    }
}
