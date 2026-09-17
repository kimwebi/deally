<?php

namespace SaasFoundation\Exceptions;

class InvitationExpiredException extends SaasException
{
    public function __construct(string $message = 'The invitation has expired', int $code = 410)
    {
        parent::__construct($message, $code);
    }
}
