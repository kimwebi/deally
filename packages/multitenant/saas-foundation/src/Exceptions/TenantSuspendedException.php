<?php

namespace SaasFoundation\Exceptions;

class TenantSuspendedException extends SaasException
{
    public function __construct(string $tenantId = '', int $code = 403)
    {
        $message = $tenantId
            ? "Tenant is suspended: {$tenantId}"
            : 'Tenant is suspended';

        parent::__construct($message, $code);
    }
}
