<?php

namespace SaasFoundation\Exceptions;

class InvalidInvitationException extends SaasException
{
    public function __construct(string $message = 'The invitation is invalid', int $code = 422)
    {
        parent::__construct($message, $code);
    }
}
