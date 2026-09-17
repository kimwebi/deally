<?php

namespace SaasFoundation\Exceptions;

class TenantNotFoundException extends SaasException
{
    public function __construct(string $identifier = '', int $code = 404)
    {
        $message = $identifier
            ? "Tenant not found: {$identifier}"
            : 'Tenant not found';

        parent::__construct($message, $code);
    }
}
