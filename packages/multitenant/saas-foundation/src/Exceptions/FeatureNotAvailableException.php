<?php

namespace SaasFoundation\Exceptions;

class FeatureNotAvailableException extends SaasException
{
    public function __construct(string $feature = '', int $code = 403)
    {
        $message = $feature
            ? "Feature not available: {$feature}"
            : 'This feature is not available on your current plan';

        parent::__construct($message, $code);
    }
}
