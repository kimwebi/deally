<?php

namespace SaasFoundation\Exceptions;

class TenantDatabaseException extends SaasException
{
    public function __construct(string $message = 'A tenant database error occurred', int $code = 500, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
