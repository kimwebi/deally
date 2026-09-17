<?php

namespace SaasFoundation\Exceptions;

class TenantNotInitializedException extends SaasException
{
    public function __construct(string $tenantId = '', int $code = 503)
    {
        $message = $tenantId
            ? "Tenant is not initialized: {$tenantId}"
            : 'Tenant is not initialized';

        parent::__construct($message, $code);
    }
}
