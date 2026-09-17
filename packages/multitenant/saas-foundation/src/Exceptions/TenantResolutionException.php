<?php

namespace SaasFoundation\Exceptions;

class TenantResolutionException extends SaasException
{
    public function __construct(string $message = 'Unable to resolve tenant from request', int $code = 404)
    {
        parent::__construct($message, $code);
    }
}
