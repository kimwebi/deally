<?php

namespace SaasFoundation\Exceptions;

class UsageLimitExceededException extends SaasException
{
    public function __construct(string $feature = '', int $code = 429)
    {
        $message = $feature
            ? "Usage limit exceeded for: {$feature}"
            : 'Usage limit exceeded';

        parent::__construct($message, $code);
    }
}
