<?php

namespace SaasFoundation\Exceptions;

class UnauthorizedTenantSwitchException extends SaasException
{
    public function __construct(string $message = 'Unauthorized tenant switch attempt', int $code = 403)
    {
        parent::__construct($message, $code);
    }
}
