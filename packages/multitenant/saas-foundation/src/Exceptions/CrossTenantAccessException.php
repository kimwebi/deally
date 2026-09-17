<?php

namespace SaasFoundation\Exceptions;

class CrossTenantAccessException extends SaasException
{
    public function __construct(string $message = 'Cross-tenant access is not allowed', int $code = 403)
    {
        parent::__construct($message, $code);
    }
}
