<?php

namespace SaasFoundation\Exceptions;

class SubscriptionRequiredException extends SaasException
{
    public function __construct(string $message = 'An active subscription is required', int $code = 402)
    {
        parent::__construct($message, $code);
    }
}
