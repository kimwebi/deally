<?php

namespace SaasFoundation\Exceptions;

class TenantAlreadyInitializedException extends SaasException
{
    public function __construct(string $tenantId = '', int $code = 409)
    {
        $message = $tenantId
            ? "Tenant is already initialized: {$tenantId}"
            : 'Tenant is already initialized';

        parent::__construct($message, $code);
    }
}
